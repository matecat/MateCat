<?php

namespace Utils\Vite;

use Utils\Registry\AppConfig;

class ViteAssets {

    private const GROUPS_PATH = 'public/vite-entries/groups.json';

    private static ?bool  $devMode = null;

    /** @var array<string, list<string>>|null */
    private static ?array $groups  = null;

    public static function isDevMode(): bool {
        if ( self::$devMode !== null ) {
            return self::$devMode;
        }
        self::$devMode = !empty( $_ENV[ 'VITE_DEV' ] ) || !empty( $_SERVER[ 'VITE_DEV' ] );

        return self::$devMode;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function loadGroups(): array {
        if ( self::$groups !== null ) {
            return self::$groups;
        }

        $path = AppConfig::$ROOT . '/' . self::GROUPS_PATH;
        if ( !file_exists( $path ) ) {
            self::$groups = [];

            return self::$groups;
        }

        $raw = file_get_contents( $path );
        if ( $raw === false ) {
            self::$groups = [];

            return self::$groups;
        }

        self::$groups = json_decode( $raw, true ) ?: [];

        return self::$groups;
    }

    public static function getHtml( string $templateName, string $nonce = '' ): string {
        if ( !self::isDevMode() ) {
            return '';
        }

        return self::buildDevHtml( $templateName, $nonce );
    }

    private static function buildDevHtml( string $templateName, string $nonce ): string {
        $groups  = self::loadGroups();
        $entries = $groups[ $templateName ] ?? [];

        if ( empty( $entries ) ) {
            return '';
        }

        $host  = AppConfig::$HTTPHOST;
        $n     = $nonce ? " nonce=\"{$nonce}\"" : '';
        $lines = [];

        $lines[] = '<script type="module"' . $n . '>'
            . "import RefreshRuntime from '/@react-refresh'\n"
            . "RefreshRuntime.injectIntoGlobalHook(window)\n"
            . 'window.$RefreshReg$ = () => {}' . "\n"
            . 'window.$RefreshSig$ = () => (type) => type' . "\n"
            . 'window.__vite_plugin_react_preamble_installed__ = true'
            . '</script>';

        $lines[] = '<script type="module" src="' . $host . '/@vite/client"' . $n . '></script>';

        foreach ( $entries as $entry ) {
            $lines[] = '<script type="module" src="' . $host . '/public/vite-entries/' . $entry . '.js"' . $n . '></script>';
        }

        return implode( "\n", $lines );
    }

}
