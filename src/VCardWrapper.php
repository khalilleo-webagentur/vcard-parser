<?php

declare(strict_types=1);

namespace Khalilleo\VCardParser;

use Exception;

final class VCardWrapper
{
    private VCard $vcard;

    /**
     * @throws Exception
     */
    public function __construct($file)
    {
        if (!is_file($file) || is_bool($file) || !is_readable($file)) {
            throw new Exception('vCard: Path not accessible (' . $file . ')');
        }

        $this->vcard = new VCard($file);

        if (count($this->vcard) == 0) {
            throw new Exception('empty vCard!');
        }
    }

    public function asJson(): string
    {
        if ($this->hasMoreThanOneRecord()) {

            $results = [];

            foreach ($this->vcard as $vCard) {
                $this->vcard = $vCard;
                $results[] = ['vCard' => $this->singleRawAsArray()];
            }

            return json_encode($results, JSON_PRETTY_PRINT);
        }

        return json_encode($this->singleRawAsArray(), JSON_PRETTY_PRINT);
    }

    public function asArray(): array
    {
        if ($this->hasMoreThanOneRecord()) {

            $results = [];

            foreach ($this->vcard as $vCard) {
                $this->vcard = $vCard;
                $results[] = ['vCard' => $this->singleRawAsArray()];
            }

            return $results;
        }

        return $this->singleRawAsArray();
    }

    private function getFirstName(): ?string
    {
        foreach ($this->vcard->N as $name) {
            return $name['FirstName'];
        }

        return null;
    }

    private function getLastName(): ?string
    {
        foreach ($this->vcard->N as $name) {
            return $name['LastName'];
        }

        return null;
    }

    private function getFullName(): ?string
    {
        foreach ($this->vcard->N as $name) {
            return $name['FirstName'] . ' ' . $name['LastName'];
        }

        return null;
    }

    private function getPhoto(): ?string
    {
        $result = null;

        if ($vCardPhoto = $this->vcard->PHOTO) {

            foreach ($vCardPhoto as $photo) {
                if ($photo['Encoding'] === 'b') {
                    $result = 'data:image/' . $photo['Type'][0] . ';base64,' . $photo['Value'];
                    break;
                }

                $result = $photo['Value'];
            }
        }

        return $result;
    }

    private function getOrganization(): ?string
    {
        $result = null;

        if ($vCardOrganization = $this->vcard->ORG) {
            foreach ($vCardOrganization as $organization) {
                $result = $organization['Name'] || $organization['Unit2']
                    ? implode(', ', [$organization['Unit1'], $organization['Unit2']])
                    : null;
                break;
            }
        }

        return $result;
    }

    private function getPhones(): array
    {
        $results = [];

        if ($vCardPhones = $this->vcard->TEL) {
            foreach ($vCardPhones as $phone) {
                if (is_scalar($phone)) {
                    $results[] = [
                        'phoneNumber' => $phone,
                        'type' => 'other',
                    ];
                } else {
                    $results[] = [
                        'phoneNumber' => $phone['Value'],
                        'type' => implode(', ', $phone['Type']),
                    ];
                }
            }
        }

        return $results;
    }

    private function getEmails(): array
    {
        $results = [];

        if ($vCardEmails = $this->vcard->EMAIL) {

            foreach ($vCardEmails as $email) {
                if (is_scalar($email)) {
                    $results[] = [
                        'email' => $email,
                        'type' => 'other',
                    ];
                } else {
                    $results[] = [
                        'email' => $email['Value'],
                        'type' => implode(', ', $email['Type']),
                    ];
                }
            }
        }

        return $results;
    }

    private function getURLs(): array
    {
        $results = [];

        if ($vCardUrls = $this->vcard->URL) {

            foreach ($vCardUrls as $url) {
                if (is_scalar($url)) {
                    $results[] = [
                        'url' => $url,
                    ];
                } else {
                    $results[] = [
                        'url' => $url['Value'],
                    ];
                }
            }
        }

        return $results;
    }

    private function getAddresses(): array
    {
        $results = [];

        if ($vCardAddresses = $this->vcard->ADR) {

            foreach ($vCardAddresses as $address) {
                $results[] = [
                    'type' => isset($address['Type']) ? implode(', ', $address['Type']) : 'other',
                    'StreetAddress' => $address['StreetAddress'] ?? '',
                    'PoBox' => $address['POBox'] ?? '',
                    'ExtendedAddress' => $address['ExtendedAddress'] ?? '',
                    'Locality' => $address['Locality'] ?? '',
                    'Region' => $address['Region'] ?? '',
                    'PostCode' => $address['PostCode'] ?? '',
                    'Country' => $address['Country'] ?? '',
                ];
            }
        }
        return $results;
    }

    private function hasMoreThanOneRecord(): bool
    {
        return $this->vcard->count() > 1;
    }

    private function singleRawAsArray(): array
    {
        return [
            'firstName' => $this->getFirstName(),
            'lastName' => $this->getLastName(),
            'fullName' => $this->getFullName(),
            'photo' => $this->getPhoto(),
            'organization' => $this->getOrganization(),
            'phones' => $this->getPhones(),
            'emails' => $this->getEmails(),
            'urls' => $this->getURLs(),
            'addresses' => $this->getAddresses(),
        ];
    }
}