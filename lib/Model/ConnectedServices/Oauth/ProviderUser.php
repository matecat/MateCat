<?php

namespace Model\ConnectedServices\Oauth;

class ProviderUser
{

    public string  $name      = "";
    public ?string $lastName  = "";
    public string  $email     = "";
    public string  $authToken = "";
    public ?string $picture   = null;
    public string  $provider  = "";

}