<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\Question;

use ilub\plugin\SelfEvaluation\DatabaseHelper\hasDBFields;
use SimpleXMLElement;
use ilDBInterface;
use ilub\plugin\SelfEvaluation\DatabaseHelper\ArrayForDB;

abstract class Question implements hasDBFields
{
    use ArrayForDB;

    public const TABLE_NAME = "";
    public const PRIMARY_KEY = 'id';
    protected int $position = 99;
    protected int $parent_id;

    public function __construct(protected ilDBInterface $db, protected int $id = 0)
    {
        if ($this->id !== 0) {
            $this->read();
        }
    }

    abstract public function cloneTo(int $parent_id): Question;

    abstract public function toXml(SimpleXMLElement $xml): SimpleXMLElement;

    abstract public static function fromXml(
        ilDBInterface $db,
        int $parent_id,
        SimpleXMLElement $xml
    ): SimpleXMLElement;

    public function read(): void
    {
        $set = $this->db->query(
            'SELECT * FROM ' . static::TABLE_NAME . ' ' . ' WHERE ' . static::PRIMARY_KEY . ' = ' . $this->getId()
        );

        $this->setObjectValuesFromRecord($this, $this->db->fetchObject($set));
    }

    final public function initDB(): void
    {
        if (!$this->db->tableExists(static::TABLE_NAME)) {
            $this->db->createTable(static::TABLE_NAME, $this->getArrayForDbWithAttributes());
            $this->db->addPrimaryKey(static::TABLE_NAME, [static::PRIMARY_KEY]);
            $this->db->createSequence(static::TABLE_NAME);
        }
    }

    final public function updateDB(): void
    {
        if (!$this->db->tableExists(static::TABLE_NAME)) {
            $this->initDB();
            return;
        }
        foreach ($this->getArrayForDbWithAttributes() as $property => $attributes) {
            if (!$this->db->tableColumnExists(static::TABLE_NAME, $property)) {
                $this->db->addTableColumn(static::TABLE_NAME, $property, $attributes);
            }
        }
    }

    public function create(): void
    {
        if ($this->getId() != 0) {
            $this->update();

            return;
        }
        $this->setId($this->db->nextId(static::TABLE_NAME));
        $this->setPosition($this->getNextPosition());
        $this->db->insert(static::TABLE_NAME, $this->getArrayForDb());
    }

    public function delete(): int
    {
        return $this->db->manipulate(
            'DELETE FROM ' . static::TABLE_NAME . ' WHERE ' . static::PRIMARY_KEY . ' = ' . $this->getId()
        );
    }

    public function update(): void
    {
        if ($this->getId() == 0) {
            $this->create();
            return;
        }
        $this->db->update(static::TABLE_NAME, $this->getArrayForDb(), $this->getIdForDb());
    }

    public function setParentId(int $parent_id): void
    {
        $this->parent_id = $parent_id;
    }

    public function getParentId(): ?int
    {
        return $this->parent_id;
    }

    protected static function _getAllInstancesForParentIdGetQuery(ilDBInterface $db, int $parent_id): \ilDBStatement
    {
        return $db->query(
            'SELECT * FROM ' . static::TABLE_NAME . ' ' . ' WHERE parent_id = '
            . $db->quote($parent_id, 'integer') . ' ORDER BY position ASC'
        );
    }

    protected function getNextPosition(): int
    {
        $set = $this->db->query(
            'SELECT MAX(position) next_pos FROM ' . static::TABLE_NAME
            . ' ' . ' WHERE parent_id = ' . $this->parent_id
        );
        while ($rec = $this->db->fetchObject($set)) {
            return $rec->next_pos + 1;
        }

        return 1;
    }

    /**
     * @return Question[]
     */
    abstract public static function _getAllInstancesForParentId(
        ilDBInterface $db,
        int $parent_id
    ): array;

    public static function _getAllInstancesForParentIdQuery(ilDBInterface $db, int $parent_id): \ilDBStatement
    {
        return $db->query(
            'SELECT * FROM ' . static::TABLE_NAME . ' ' . ' WHERE parent_id = '
            . $db->quote($parent_id, 'integer') . ' ORDER BY position ASC'
        );
    }

    abstract public static function _getAllInstancesForParentIdAsArray(ilDBInterface $db, int $parent_id): array;

    public static function _isObject(ilDBInterface $db, int $id): bool
    {
        $set = $db->query('SELECT id FROM ' . static::TABLE_NAME . ' WHERE ' . static::PRIMARY_KEY . ' = ' . $id);

        while ($db->fetchObject($set)) {
            return true;
        }

        return false;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    abstract public function getTitle(): string;

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}
