<?php

declare(strict_types=1);

namespace Khalilleo\VCardParser;

use Countable;
use Exception;
use Iterator;

/**
 * vCard class for parsing a vCard and/or creating one
 *
 * @link https://github.com/nuovo/vCard-parser
 * @author Martins Pilsetnieks, Roberts Bruveris
 * @see RFC 2426, RFC 2425
 * @version 0.4.8
 */
class VCard implements Countable, Iterator
{
    private const MODE_ERROR = 'error';
    private const MODE_SINGLE = 'single';
    private const MODE_MULTIPLE = 'multiple';

    const endLine = "\n";

    /**
     * @static Parts of structured elements according to the spec.
     *
     * single, multiple, error
     */
    private static array $specStructuredElements = [
        'n' => ['LastName', 'FirstName', 'AdditionalNames', 'Prefixes', 'Suffixes'],
        'adr' => ['POBox', 'ExtendedAddress', 'StreetAddress', 'Locality', 'Region', 'PostalCode', 'Country'],
        'geo' => ['Latitude', 'Longitude'],
        'org' => ['Name', 'Unit1', 'Unit2']
    ];

    private static array $specMultipleValueElements = ['nickname', 'categories'];

    private static array $specElementTypes = [
        'email' => ['internet', 'x400', 'pref', 'other'],
        'adr' => ['dom', 'intl', 'postal', 'parcel', 'home', 'work', 'pref'],
        'label' => ['dom', 'intl', 'postal', 'parcel', 'home', 'work', 'pref'],
        'tel' => ['home', 'msg', 'work', 'pref', 'other', 'fax', 'cell', 'video', 'pager', 'bbs', 'modem', 'car', 'isdn', 'pcs'],
        'impp' => ['personal', 'business', 'home', 'work', 'mobile', 'pref']
    ];

    private static array $specFileElements = ['photo', 'logo', 'sound'];

    /**
     * @var string Current object mode - error, single or multiple (for a single vCard within a file and multiple combined vCards)
     */
    private string $mode;

    private ?string $path = null;

    private null|string|array $rawData = null;

    /**
     * @var array Internal options' container. Options:
     * bool collapse: If true, elements that can have multiple values but have only a single value are returned
     * as that value instead of an array if false, an array is returned even if it has only one value.
     */
    private array $options = [
        'collapse' => false
    ];

    /**
     * @var array Internal data container. Contains vCard objects for multiple vCards and just the data for single vCards.
     */
    private array $data = [];

    /**
     * vCard constructor
     * bool collapse: If true, elements that can have multiple values but have only a single value are returned
     * as that value instead of an array If false, an array is returned even if it has only one value.
     * One of these parameters must be provided, otherwise an exception is thrown.
     * @throws Exception
     */
    public function __construct(string|bool $path = null, string $rawData = null, array $options = [])
    {
        // Checking preconditions for the parser.
        // If path is given, the file should be accessible.
        // If raw data is given, it is taken as it is.
        // In both cases the real content is put in $this -> RawData

        if ($path) {
            $this->path = $path;
            $this->rawData = file_get_contents($this->path);
        } elseif ($rawData) {
            $this->rawData = $rawData;
        } else {
            // Not necessary anymore as possibility to create vCards is added
            throw new Exception('vCard: No content provided');
        }

        if (!$this->path && !$this->rawData) {
            return true;
        }

        if ($options) {
            $this->options = array_merge($this->options, $options);
        }

        // Counting the beginning/end separators. If there aren't any or the count doesn't match, there is a problem with the file.
        // If there is only one, this is a single vCard, if more, multiple vCards are combined.
        $Matches = [];
        $vCardBeginCount = preg_match_all('{^BEGIN\:VCARD}miS', $this->rawData, $Matches);
        $vCardEndCount = preg_match_all('{^END\:VCARD}miS', $this->rawData, $Matches);

        if (($vCardBeginCount != $vCardEndCount) || !$vCardBeginCount) {
            $this->mode = vCard::MODE_ERROR;
            throw new Exception('vCard: invalid vCard');
        }

        $this->mode = $vCardBeginCount == 1 ? vCard::MODE_SINGLE : vCard::MODE_MULTIPLE;

        // Removing/changing inappropriate newlines, i.e., all CRs or multiple newlines are changed to a single newline
        $this->rawData = str_replace("\r", "\n", $this->rawData);
        $this->rawData = preg_replace('{(\n+)}', "\n", $this->rawData);

        // In multiple card mode the raw text is split at card beginning markers and each
        //	fragment is parsed in a separate vCard object.
        if ($this->mode == self::MODE_MULTIPLE) {
            $this->rawData = explode('BEGIN:VCARD', $this->rawData);
            $this->rawData = array_filter($this->rawData);

            foreach ($this->rawData as $singleVCardRawData) {
                // Prepending "BEGIN:VCARD" to the raw string because we exploded on that one.
                // If there won't be the BEGIN marker in the new object, it will fail.
                $singleVCardRawData = 'BEGIN:VCARD' . "\n" . $singleVCardRawData;

                $ClassName = get_class($this);
                $this->data[] = new $ClassName(false, $singleVCardRawData);
            }
        } else {
            // Protect the BASE64 final = sign (detected by the line beginning with whitespace), otherwise the next replace will get rid of it
            $this->rawData = preg_replace('{(\n\s.+)=(\n)}', '$1-base64=-$2', $this->rawData);

            // Joining multiple lines that are split with a hard wrap and indicated by an equals sign at the end of line
            // (quoted-printable-encoded values in v2.1 vCards)
            $this->rawData = str_replace("=\n", '', $this->rawData);

            // Joining multiple lines that are split with a soft wrap (space or tab on the beginning of the next line
            $this->rawData = str_replace(["\n ", "\n\t"], '-wrap-', $this->rawData);

            // Restoring the BASE64 final equals sign (see a few lines above)
            $this->rawData = str_replace("-base64=-\n", "=\n", $this->rawData);

            $Lines = explode("\n", $this->rawData);

            foreach ($Lines as $Line) {
                // Lines without colons are skipped because, most likely, they contain no data.
                if (!str_contains($Line, ':')) {
                    continue;
                }

                // Each line is split into two parts. The key contains the element name and additional parameters, if present,
                //	value is just the value
                [$Key, $Value] = explode(':', $Line, 2);

                // Key is transformed to lowercase because, even though the element and parameter names are written in uppercase,
                //	it is quite possible that they will be in lower- or mixed case.
                // The key is trimmed to allow for non-significant WSP characters as allowed by v2.1
                $Key = strtolower(trim(self::unescape($Key)));

                // These two lines can be skipped as they aren't necessary at all.
                if ($Key == 'begin' || $Key == 'end') {
                    continue;
                }

                if ((str_starts_with($Key, 'agent')) && (stripos($Value, 'begin:vcard') !== false)) {
                    $ClassName = get_class($this);
                    $Value = new $ClassName(false, str_replace('-wrap-', "\n", $Value));
                    if (!isset($this->data[$Key])) {
                        $this->data[$Key] = [];
                    }
                    $this->data[$Key][] = $Value;
                    continue;
                } else {
                    $Value = str_replace('-wrap-', '', $Value);
                }

                $Value = trim(self::unescape($Value));
                $Type = [];

                // Here additional parameters are parsed
                $KeyParts = explode(';', $Key);
                $Key = $KeyParts[0];
                $Encoding = false;

                if (str_starts_with($Key, 'item')) {
                    $TmpKey = explode('.', $Key, 2);
                    $Key = $TmpKey[1];
                    $ItemIndex = (int)str_ireplace('item', '', $TmpKey[0]);
                }

                if (count($KeyParts) > 1) {
                    $Parameters = self::parseParameters($Key, array_slice($KeyParts, 1));

                    foreach ($Parameters as $ParamKey => $ParamValue) {
                        switch ($ParamKey) {
                            case 'encoding':
                                $Encoding = $ParamValue;
                                if (in_array($ParamValue, ['b', 'base64'])) {
                                    //$Value = base64_decode($Value);
                                } elseif ($ParamValue == 'quoted-printable') {  // v2.1
                                    $Value = quoted_printable_decode($Value);
                                }
                                break;
                            case 'charset': // v2.1
                                if ($ParamValue != 'utf-8' && $ParamValue != 'utf8') {
                                    $Value = mb_convert_encoding($Value, 'UTF-8', $ParamValue);
                                }
                                break;
                            case 'type':
                                $Type = $ParamValue;
                                break;
                        }
                    }
                }

                // Checking files for colon-separated additional parameters (Apple's Address Book does this), for example, "X-ABCROP-RECTANGLE" for photos
                if (in_array($Key, self::$specFileElements) && isset($Parameters['encoding']) && in_array($Parameters['encoding'], ['b', 'base64'])) {
                    // If colon is present in the value, it must contain Address Book parameters
                    //	(colon is an invalid character for base64 so it shouldn't appear in valid files)
                    if (str_contains($Value, ':')) {
                        $Value = explode(':', $Value);
                        $Value = array_pop($Value);
                    }
                }

                // Values are parsed according to their type
                if (isset(self::$specStructuredElements[$Key])) {
                    $Value = self::parseStructuredValue($Value, $Key);
                    if ($Type) {
                        $Value['Type'] = $Type;
                    }
                } else {
                    if (in_array($Key, self::$specMultipleValueElements)) {
                        $Value = self::parseMultipleTextValue($Value, $Key);
                    }

                    if ($Type) {
                        $Value = [
                            'Value' => $Value,
                            'Type' => $Type
                        ];
                    }
                }

                if (is_array($Value) && $Encoding) {
                    $Value['Encoding'] = $Encoding;
                }

                if (!isset($this->data[$Key])) {
                    $this->data[$Key] = [];
                }

                $this->data[$Key][] = $Value;
            }
        }
    }

    /**
     * Removes the escaping slashes from the text.
     * @param string $text Text to prepare.
     * @return string Resulting text.
     */
    private static function unescape(string $text): string
    {
        return str_replace(['\:', '\;', '\,', "\n"], [':', ';', ',', ''], $text);
    }

    private static function parseParameters($key, array $rawParams = null): array
    {
        if (!$rawParams) {
            return [];
        }

        // Parameters are split into (key, value) pairs
        $Parameters = [];
        foreach ($rawParams as $Item) {
            $Parameters[] = explode('=', strtolower($Item));
        }

        $Type = [];
        $Result = [];

        // And each parameter is checked whether anything can/should be done because of it
        foreach ($Parameters as $Index => $Parameter) {
            // Skipping empty elements
            if (!$Parameter) {
                continue;
            }

            // Handling type parameters without the explicit TYPE parameter name (2.1 valid)
            if (count($Parameter) == 1) {
                // Checks if the type value is allowed for the specific element
                // The second part of the "if" statement means that email elements can have non-standard types (see the spec)
                if (
                    (isset(self::$specElementTypes[$key]) && in_array($Parameter[0], self::$specElementTypes[$key])) ||
                    ($key == 'email' && is_scalar($Parameter[0]))
                ) {
                    $Type[] = $Parameter[0];
                }
            } elseif (count($Parameter) > 2) {
                $TempTypeParams = self::parseParameters($key, explode(',', $rawParams[$Index]));
                if ($TempTypeParams['type']) {
                    $Type = array_merge($Type, $TempTypeParams['type']);
                }
            } else {
                switch ($Parameter[0]) {
                    case 'encoding':
                        if (in_array($Parameter[1], ['quoted-printable', 'b', 'base64'])) {
                            $Result['encoding'] = $Parameter[1] == 'base64' ? 'b' : $Parameter[1];
                        }
                        break;
                    case 'charset':
                        $Result['charset'] = $Parameter[1];
                        break;
                    case 'type':
                        $Type = array_merge($Type, explode(',', $Parameter[1]));
                        break;
                    case 'value':
                        if (strtolower($Parameter[1]) == 'url') {
                            $Result['encoding'] = 'uri';
                        }
                        break;
                }
            }
        }

        $Result['type'] = $Type;

        return $Result;
    }

    /**
     * Separates the various parts of a structured value according to the spec.
     *
     * @param string $text Raw text string
     * @param string $key Key (e.g., N, ADR, ORG, etc.)
     */
    private static function parseStructuredValue(string $text, string $key): array
    {
        $text = array_map('trim', explode(';', $text));

        $result = [];

        foreach (self::$specStructuredElements[$key] as $Index => $structurePart) {
            $result[$structurePart] = $text[$Index] ?? null;
        }
        return $result;
    }

    private static function parseMultipleTextValue(string $text): array
    {
        return explode(',', $text);
    }

    /**
     * Saves an embedded file
     *
     * @param string $key Key
     * @param int    $index Index of the file, defaults to 0
     * @param string $targetPath Target path where the file should be saved, including the filename
     *
     * @throws Exception
     */
    public function saveFile(string $key, int $index = 0, string $targetPath = ''): bool
    {
        if (!isset($this->data[$key])) {
            return false;
        }
        if (!isset($this->data[$key][$index])) {
            return false;
        }

        // Returning false if it is an image URL
        if (stripos($this->data[$key][$index]['Value'], 'uri:') === 0) {
            return false;
        }

        if (is_writable($targetPath) || (!file_exists($targetPath) && is_writable(dirname($targetPath)))) {
            $RawContent = $this->data[$key][$index]['Value'];
            if (isset($this->data[$key][$index]['Encoding']) && $this->data[$key][$index]['Encoding'] == 'b') {
                $RawContent = base64_decode($RawContent);
            }
            $Status = file_put_contents($targetPath, $RawContent);
            return (bool)$Status;
        }

        throw new Exception('vCard: Cannot save file (' . $key . '), target path not writable (' . $targetPath . ')');
    }

    /**
     * Magic method to get the various vCard values as object members, e.g. a call to $vCard -> N gets the "N" value
     */
    public function __get(string $key)
    {
        $key = strtolower($key);

        if (isset($this->data[$key])) {
            if ($key == 'agent') {
                return $this->data[$key];
            } elseif (in_array($key, self::$specFileElements)) {
                $Value = $this->data[$key];
                foreach ($Value as $K => $V) {
                    if (stripos($V['Value'], 'uri:') === 0) {
                        $Value[$K]['Value'] = substr($V, 4);
                        $Value[$K]['Encoding'] = 'uri';
                    }
                }
                return $Value;
            }

            if ($this->options['collapse'] && is_array($this->data[$key]) && (count($this->data[$key]) == 1)) {
                return $this->data[$key][0];
            }
            return $this->data[$key];
        } elseif ($key == 'Mode') {
            return $this->mode;
        }
        return [];
    }

    /**
     * Magic method for adding data to the vCard
     *
     * @param string $key Key
     * @param array  $arguments Method call arguments. First element is value.
     *
     * @return vCard Current object for method chaining
     */
    public function __call(string $key, array $arguments)
    {
        $key = strtolower($key);

        if (!isset($this->data[$key])) {
            $this->data[$key] = [];
        }

        $Value = $arguments[0] ?? false;

        if (count($arguments) > 1) {
            $Types = array_values(array_slice($arguments, 1));

            if (isset(self::$specStructuredElements[$key]) &&
                in_array($arguments[1], self::$specStructuredElements[$key])
            ) {
                $LastElementIndex = 0;

                if (count($this->data[$key])) {
                    $LastElementIndex = count($this->data[$key]) - 1;
                }

                if (isset($this->data[$key][$LastElementIndex])) {
                    if (empty($this->data[$key][$LastElementIndex][$Types[0]])) {
                        $this->data[$key][$LastElementIndex][$Types[0]] = $Value;
                    } else {
                        $LastElementIndex++;
                    }
                }

                if (!isset($this->data[$key][$LastElementIndex])) {
                    $this->data[$key][$LastElementIndex] = [
                        $Types[0] => $Value
                    ];
                }
            } elseif (isset(self::$specElementTypes[$key])) {
                $this->data[$key][] = [
                    'Value' => $Value,
                    'Type' => $Types
                ];
            }
        } elseif ($Value) {
            $this->data[$key][] = $Value;
        }

        return $this;
    }

    /**
     * Magic method for getting vCard content out
     *
     * @return string Raw vCard content
     */
    public function __toString()
    {
        $Text = 'BEGIN:VCARD' . self::endLine;
        $Text .= 'VERSION:3.0' . self::endLine;

        foreach ($this->data as $Key => $Values) {
            $KeyUC = strtoupper($Key);
            $Key = strtolower($Key);

            if (in_array($KeyUC, ['PHOTO', 'VERSION'])) {
                continue;
            }

            foreach ($Values as $Index => $Value) {
                $Text .= $KeyUC;
                if (is_array($Value) && isset($Value['Type'])) {
                    $Text .= ';TYPE=' . self::prepareTypeStrForOutput($Value['Type']);
                }
                $Text .= ':';

                if (isset(self::$specStructuredElements[$Key])) {
                    $PartArray = [];
                    foreach (self::$specStructuredElements[$Key] as $Part) {
                        $PartArray[] = $Value[$Part] ?? '';
                    }
                    $Text .= implode(';', $PartArray);
                } elseif (is_array($Value) && isset(self::$specElementTypes[$Key])) {
                    $Text .= $Value['Value'];
                } else {
                    $Text .= $Value;
                }

                $Text .= self::endLine;
            }
        }

        $Text .= 'END:VCARD' . self::endLine;

        return $Text;
    }

    private static function prepareTypeStrForOutput($type): string
    {
        return implode(',', array_map('strtoupper', $type));
    }

    public function count(): int
    {
        return match ($this->mode) {
            self::MODE_SINGLE => 1,
            self::MODE_MULTIPLE => count($this->data),
            default => 0,
        };
    }
    public function rewind(): void
    {
        reset($this->data);
    }

    public function next(): mixed
    {
        return next($this->data);
    }

    public function valid(): bool
    {
        return ($this->current() !== false);
    }

    public function current(): mixed
    {
        return current($this->data);
    }

    public function key(): mixed
    {
        return key($this->data);
    }
}