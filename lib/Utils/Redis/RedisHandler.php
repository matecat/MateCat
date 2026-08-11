<?php

namespace Utils\Redis;

use Exception;
use InvalidArgumentException;
use Predis\Client;
use Utils\Registry\AppConfig;

/**
 * Redis connection handler with support for multiple Predis connection modes.
 *
 * Manages lazy Redis connections via Predis\Client, with automatic health checks (ping-based).
 *
 * ## Configuration
 *
 * All settings are read from {@see AppConfig} static properties, which are loaded
 * from the INI config file (inc/config.ini) at bootstrap.
 *
 * ### Mode 1: Single Node (default — current behavior)
 *
 *     ; config.ini
 *     REDIS_MODE    = "single"
 *     REDIS_SERVERS = "tcp://redis:6379"
 *
 *     Connects to a single Redis instance. This is the default when REDIS_MODE
 *     is omitted. Backward-compatible with all existing configurations.
 *
 * ### Mode 2: Redis Cluster (native, Redis 3.0+)
 *
 *     ; config.ini
 *     REDIS_MODE    = "cluster"
 *     REDIS_SERVERS = "tcp://node1:7000,tcp://node2:7001,tcp://node3:7002"
 *
 *     Uses Redis's native cluster protocol (MOVED/ASK redirections).
 *     Predis option: ['cluster' => 'redis']
 *     All nodes must be listed. Predis auto-discovers the full topology.
 *     Note: MULTI/EXEC transactions are limited to keys on the same slot.
 *
 * ### Mode 3: Master-Slave Replication (client-side, manual)
 *
 *     ; config.ini
 *     REDIS_MODE    = "replication"
 *     REDIS_SERVERS = "tcp://master:6379,tcp://slave1:6380,tcp://slave2:6381"
 *
 *     Predis option: ['replication' => 'predis']
 *     The master MUST be named with "?role=master" on its own DSN. Position means nothing:
 *     Predis assigns the master role only from that parameter and has no positional
 *     default, so a set that names none serves every read from a randomly chosen replica
 *     and fails every write with "No master server available for replication".
 *     Servers without the parameter are treated as read-only replicas.
 *     Predis then routes read commands (GET, HGET, etc.) to replicas and write commands
 *     (SET, DEL, etc.) to the master automatically.
 *     No automatic failover — if the master goes down, manual intervention is needed.
 *
 * ### Mode 4: Sentinel (automatic failover)
 *
 *     ; config.ini
 *     REDIS_MODE             = "sentinel"
 *     REDIS_SERVERS          = "tcp://sentinel1:26379,tcp://sentinel2:26379,tcp://sentinel3:26379"
 *     REDIS_SENTINEL_SERVICE = "mymaster"
 *
 *     Predis option: ['replication' => 'sentinel', 'service' => '<name>']
 *     Sentinel nodes monitor a master and its replicas. Predis queries the sentinels
 *     to discover the current master, and automatically switches on failover.
 *     REDIS_SERVERS lists sentinel nodes (NOT the Redis data nodes).
 *     REDIS_SENTINEL_SERVICE is the monitored service name (default: "mymaster").
 *
 * ### Optional (all modes)
 *
 *     ; config.ini
 *     REDIS_PASSWORD = "secret"
 *
 *     When set, Predis sends AUTH before any command. Applied to all connections
 *     (single, cluster nodes, sentinel nodes, and discovered master/replicas).
 *
 * ### Database selection (INSTANCE_ID)
 *
 *     AppConfig::$INSTANCE_ID controls the Redis database number.
 *     When non-zero, "?database=N" is appended to each DSN.
 *     This is applied in all modes. In cluster mode, only database 0 is supported
 *     by Redis — set INSTANCE_ID = 0 when using cluster mode.
 *
 * @see https://github.com/predis/predis for Predis documentation
 */
class RedisHandler
{
    private ?Client $redisClient = null;

    /**
     * Lazy connection — returns a connected Predis Client, reconnecting if the
     * previous connection is dead (verified via PING).
     *
     * @throws Exception
     */
    public function getConnection(): Client
    {
        if ($this->redisClient !== null) {
            try {
                $this->redisClient->ping();

                return $this->redisClient;
            } catch (Exception) {
                // Connection dead — fall through to reconnect
            }
        }

        $this->redisClient = $this->getClient();

        return $this->redisClient;
    }

    /**
     * Build a Predis Client based on AppConfig settings.
     *
     * @throws InvalidArgumentException When REDIS_MODE is not a recognized value, or when
     *                                 replication mode names no master.
     */
    private function getClient(): Client
    {
        $servers = $this->resolveServers();
        $mode    = AppConfig::$REDIS_MODE;

        if ($mode === 'replication') {
            $this->assertReplicationSetNamesAMaster($servers);
        }

        /** @var array<string, mixed> $options */
        $options = [];

        if (AppConfig::$REDIS_PASSWORD !== null) {
            $options['parameters']['password'] = AppConfig::$REDIS_PASSWORD;
        }

        return match ($mode) {
            'single'      => new Client($servers[0] ?? '', $options ?: null),
            'cluster'     => new Client($servers, ['cluster' => 'redis'] + $options),
            'replication' => new Client($servers, ['replication' => 'predis'] + $options),
            'sentinel'    => new Client(
                $servers,
                [
                    'replication' => 'sentinel',
                    'service'     => AppConfig::$REDIS_SENTINEL_SERVICE,
                ] + $options
            ),
            default => throw new InvalidArgumentException(
                "Unknown REDIS_MODE: '$mode'. Valid values: single, cluster, replication, sentinel"
            ),
        };
    }

    /**
     * Refuse a replication set that names no master, instead of letting the first write discover it.
     *
     * Predis does not infer the master from position — the documentation above used to say it did.
     * The role is assigned only from an explicit `role=master` parameter on the DSN, with no
     * positional fallback, so a set without one sends every read to a randomly picked replica and
     * throws `No master server available for replication` on the first write.
     *
     * Failing at connection time rather than at that write is the point. This class is what the DAO
     * cache and the login token ring connect through, and authenticate() catches Throwable so a
     * request degrades to signed-out rather than erroring: a misconfigured replication set would
     * therefore not present as a configuration problem at all, it would present as every user being
     * logged out while writes failed silently underneath. Naming the missing parameter cannot be
     * mistaken for anything else.
     *
     * This can only reject a configuration that is already broken. A replication set whose DSNs
     * carry the parameter passes unchanged, and one that does not cannot complete a write today.
     *
     * @param list<string> $servers
     *
     * @throws InvalidArgumentException
     */
    private function assertReplicationSetNamesAMaster(array $servers): void
    {
        foreach ($servers as $dsn) {
            $query = parse_url($dsn, PHP_URL_QUERY);

            if (!is_string($query)) {
                continue;
            }

            parse_str($query, $parameters);

            if (($parameters['role'] ?? null) === 'master') {
                return;
            }
        }

        throw new InvalidArgumentException(
            'REDIS_MODE = "replication" requires the master to be named in REDIS_SERVERS with '
            . '"?role=master", for example '
            . '"tcp://master:6379?role=master,tcp://replica:6380". Predis has no positional '
            . 'default: without it every read goes to a random replica and every write fails with '
            . '"No master server available for replication".'
        );
    }

    /**
     * Parse REDIS_SERVERS into a list of DSN strings with INSTANCE_ID database appended.
     *
     * Accepts a single DSN string ("tcp://host:port"), a comma-separated string
     * ("tcp://a:6379,tcp://b:6380"), or a list of DSN strings.
     *
     * @return list<string>
     */
    private function resolveServers(): array
    {
        $raw = AppConfig::$REDIS_SERVERS;

        if (is_string($raw)) {
            $parts = array_map('trim', explode(',', $raw));
        } else {
            $parts = $raw;
        }

        return array_values(array_map([$this, 'formatDSN'], $parts));
    }

    /**
     * Append "?database=N" to a DSN when INSTANCE_ID is non-zero.
     */
    protected function formatDSN(string $dsnString): string
    {
        if (AppConfig::$INSTANCE_ID !== 0) {
            $conf = parse_url($dsnString);

            if (isset($conf['query'])) {
                $instanceID = "&database=" . AppConfig::$INSTANCE_ID;
            } else {
                $instanceID = "?database=" . AppConfig::$INSTANCE_ID;
            }

            return $dsnString . $instanceID;
        }

        return $dsnString;
    }

}
