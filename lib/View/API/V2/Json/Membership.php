<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 15:01
 */

namespace View\API\V2\Json;


use Exception;
use Model\Teams\MembershipStruct;
use ReflectionException;
use RuntimeException;

class Membership
{

    /**
     * @var MembershipStruct[]
     */
    protected array $data;

    /**
     * @param MembershipStruct[] $data
     *
     * @throws \TypeError
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public function renderItem(MembershipStruct $membership): array
    {
        $out = [
            'id' => $membership->id ?? 0,
            'id_team' => $membership->id_team,
        ];

        $out['user'] = User::renderItem($membership->getUser());

        $metadata = UserMetadata::renderMetadataCollection($membership->getUserMetadata());
        if (!empty($metadata)) {
            $out['user_metadata'] = array_filter($metadata);
        }

        $out['projects'] = $membership->getAssignedProjects();

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public function render(): array
    {
        $out = [];
        foreach ($this->data as $membership) {
            $out[] = $this->renderItem($membership);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public function renderPublic(): array
    {
        $out = [];
        foreach ($this->data as $membership) {
            $render = $this->renderItemPublic($membership);
            if ($render) {
                $out[] = $render;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|false
     *
     * @throws Exception
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public function renderItemPublic(MembershipStruct $membership): false|array
    {
        return User::renderItemPublic($membership->getUser());
    }


}