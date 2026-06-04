<?php

declare(strict_types=1);

class ilSelfEvaluationConfig
{
    protected ilDBInterface $db;

    public function __construct(protected string $table_name)
    {
        global $DIC;

        $this->db = $DIC->database();
    }

    public function setTableName(string $table_name): void
    {
        $this->table_name = $table_name;
    }

    public function getTableName(): string
    {
        return $this->table_name;
    }

    public function __call(string $method, array $params): string|bool|null
    {
        if (str_starts_with($method, 'get')) {
            return $this->getValue(self::_fromCamelCase(substr($method, 3)));
        }
        if (str_starts_with($method, 'set')) {
            $this->setValue(self::_fromCamelCase(substr($method, 3)), $params[0]);

            return true;
        }
        return null;
    }

    public function setValue(string $key, string $value): void
    {
        if (!is_string($this->getValue($key))) {
            $this->db->insert($this->getTableName(), [
                "config_key" => [
                    "text",
                    $key
                ],
                "config_value" => [
                    "text",
                    $value
                ]
            ]);
        } else {
            $this->db->update($this->getTableName(), [
                "config_key" => [
                    "text",
                    $key
                ],
                "config_value" => [
                    "text",
                    $value
                ]
            ], [
                "config_key" => [
                    "text",
                    $key
                ]
            ]);
        }
    }

    public function getValue(string $key): string
    {
        $result = $this->db->query(
            "SELECT config_value FROM " . $this->getTableName() . " WHERE config_key = "
            . $this->db->quote($key, "text")
        );
        if ($result->numRows() === 0) {
            return '';
        }
        $record = $this->db->fetchAssoc($result);

        return (string) $record['config_value'];
    }

    public function getContainer(): bool|int|string
    {
        $key = $this->getValue('container');
        if ($key === '' || $key == 0) {
            return 1;
        }
        return $key;
    }

    public function initDB(): bool
    {
        if (!$this->db->tableExists($this->getTableName())) {
            $fields = [
                'config_key' => [
                    'type' => 'text',
                    'length' => 128,
                    'notnull' => true
                ],
                'config_value' => [
                    'type' => 'clob',
                    'notnull' => false
                ],
            ];
            $this->db->createTable($this->getTableName(), $fields);
            $this->db->addPrimaryKey($this->getTableName(), ["config_key"]);
        }

        return true;
    }

    public static function _fromCamelCase(string $str): string
    {
        $str[0] = strtolower($str[0]);

        return preg_replace_callback(
            '/([A-Z])/',
            fn($c): string => "_" . strtolower($c[1]),
            $str
        );
    }

    public static function _toCamelCase(string $str, bool $capitalise_first_char = false): string
    {
        if ($capitalise_first_char) {
            $str[0] = strtoupper($str[0]);
        }

        return preg_replace_callback(
            '/-([a-z])/',
            fn($c): string => strtoupper($c[1]),
            $str
        );
    }
}
