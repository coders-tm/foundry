<?php

namespace Foundry\Services;

class IpLocation
{
    public ?string $ip = null;

    public ?string $countryName = null;

    public ?string $countryCode = null;

    public ?string $regionCode = null;

    public ?string $regionName = null;

    public ?string $cityName = null;

    public ?string $zipCode = null;

    public ?string $isoCode = null;

    public ?string $postalCode = null;

    public ?string $latitude = null;

    public ?string $longitude = null;

    public ?string $metroCode = null;

    public ?string $areaCode = null;

    public ?string $timezone = null;

    /**
     * Create a new IpLocation instance from a source object or array.
     *
     * @param  mixed  $data
     */
    public static function fromSource($data): self
    {
        $instance = new self;

        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        if (is_array($data)) {
            $instance->ip = $data['ip'] ?? null;
            $instance->countryName = $data['countryName'] ?? null;
            $instance->countryCode = $data['countryCode'] ?? null;
            $instance->regionCode = $data['regionCode'] ?? null;
            $instance->regionName = $data['regionName'] ?? null;
            $instance->cityName = $data['cityName'] ?? null;
            $instance->zipCode = $data['zipCode'] ?? null;
            $instance->isoCode = $data['isoCode'] ?? null;
            $instance->postalCode = $data['postalCode'] ?? null;
            $instance->latitude = $data['latitude'] ?? null;
            $instance->longitude = $data['longitude'] ?? null;
            $instance->metroCode = $data['metroCode'] ?? null;
            $instance->areaCode = $data['areaCode'] ?? null;
            $instance->timezone = $data['timezone'] ?? null;
        }

        return $instance;
    }

    /**
     * Convert the DTO to an array.
     */
    public function toArray(): array
    {
        return [
            'ip' => $this->ip,
            'countryName' => $this->countryName,
            'countryCode' => $this->countryCode,
            'regionCode' => $this->regionCode,
            'regionName' => $this->regionName,
            'cityName' => $this->cityName,
            'zipCode' => $this->zipCode,
            'isoCode' => $this->isoCode,
            'postalCode' => $this->postalCode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'metroCode' => $this->metroCode,
            'areaCode' => $this->areaCode,
            'timezone' => $this->timezone,
        ];
    }
}
