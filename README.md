## vCard-Parser

vCard-parser is a simple vCard file parser with the focus on ease of use.

### Installation

`composer require khalilleo-webagentur/vcard-parser`

### Usage

$ `composer install`

$ `php -S localhost:8000 -t examples`

```php
<?php

require __DIR__ .'/../vendor/autoload.php';

use Khalilleo\VCardParser\VCardWrapper;

$vcfFileExample1 = 'Example-01.vcf';
$vcfFileExample2 = 'Example-02.vcf';
$vcfFileExample3 = 'Example-03.vcf';

$vCardWrapper = new VCardWrapper($vcfFileExample1);

echo $vCardWrapper->asJson();
```

```json
// output:
        
{
  "firstName": "John",
  "lastName": "Doe",
  "fullName": "John Doe",
  "photo": "https://k24.ing/assets/img/logo.pngImage preview",
  "organization": null,
  "phones": [
    {
      "phoneNumber": "(111) 555-1212",
      "type": "work, voice"
    },
    {
      "phoneNumber": "(404) 555-1212",
      "type": "home, voice"
    },
    {
      "phoneNumber": "(404) 555-1213",
      "type": "home, voice"
    }
  ],
  "emails": [
    {
      "email": "forrestgump@example.com",
      "type": "pref, internet"
    },
    {
      "email": "example@example.com",
      "type": "internet"
    }
  ],
  "urls": [
    {
      "url": "https://www.google.com/"
    }
  ],
  "addresses": [
    {
      "type": "home",
      "StreetAddress": "42 Plantation St.",
      "PoBox": "",
      "ExtendedAddress": "",
      "Locality": "Baytown",
      "Region": "LA",
      "PostCode": "",
      "Country": "United States of America"
    }
  ]
}

```

```php
var_export($vCardWrapper->asArray());
```

```php
// output:

array ( 
    'firstName' => 'John',
    'lastName' => 'Doe',
    'fullName' => 'John Doe',
    'photo' => 'https://k24.ing/assets/img/logo.png',
    'organization' => NULL,
    'phones' => array ( 
        0 => array ( 
            'phoneNumber' => '(111) 555-1212', 
            'type' => 'work, voice', ),
        1 => array ( 
            'phoneNumber' => '(404) 555-1212', 
            'type' => 'home, voice', ),
        2 => array (
            'phoneNumber' => '(404) 555-1213', 
            'type' => 'home, voice', ),
    ),
    'emails' => array ( 
        0 => array ( 
            'email' => 'forrestgump@example.com',
             'type' => 'pref, internet', ),
        1 => array (
            'email' => 'example@example.com',
             'type' => 'internet', ),
    ),
    'urls' => array (
        0 => array ( 'url' => 'https://www.google.com/', ), 
    ), 
    'addresses' => array ( 
          0 => array ( 
                'type' => 'home',
                 'StreetAddress' => '42 Plantation St.', 
                 'PoBox' => '', 
                 'ExtendedAddress' => '', 
                 'Locality' => 'Baytown',
                 'Region' => 'LA',
                 'PostCode' => '', 
                 'Country' => 'United States of America', 
    ),
   ),
 )
```

### Credit

[nuovo](https://github.com/nuovo/vCard-parser)

### Copyright

This project is licensed under the MIT License.
