<?php
namespace Sosupp\SlimerDesktop\Services;

class RemoteLandlordService
{
    public function __construct()
    {
        // Set landlord connection
    }

    public function getTenant(string|int $shortName)
    {
        return (object)[];
        // make remote/ api call from desktop to endpoint to get tenant

    }
}
