<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\UIHelper\Scale;

use ilDBInterface;
use ilub\plugin\SelfEvaluation\DatabaseHelper\ArrayForDB;
use ilub\plugin\SelfEvaluation\DatabaseHelper\hasDBFields;
use SimpleXMLElement;

class ScaleUnit implements hasDBFields
{
    use ArrayForDB;

    public const TABLE_NAME = 'rep_robj_xsev_scale_u';
    protected string $title = 'Standartitle';
    protected int $value = 10;
    protected int $parent_id = 0;
    protected int $position = 99;

    public function __construct(protected ilDBInterface $db, protected int $id = 0)
    {
        if ($this->id !== 0) {
            $this->read();
        }
    }

    public function cloneTo(int $parent_id): self
    {
        $clone = new self($this->db);
        $clone->setParentId($parent_id);
        $clone->setTitle($this->getTitle());
        $clone->setValue($this->getValue());
        $clone->setPosition($this->getPosition());
        $clone->update();
        return $clone;
    }

    public function toXml(int $parent_id, SimpleXMLElement $xml): SimpleXMLElement
    {
        $child_xml = $xml->addChild("scaleUnit");
        $child_xml->addAttribute("parentId", (string) $parent_id);
        $child_xml->addAttribute("title", $this->getTitle());
        $child_xml->addAttribute("value", (string) $this->getValue());
        $child_xml->addAttribute("position", (string) $this->getPosition());
        return $xml;
    }

    public static function fromXml(ilDBInterface $db, int $parent_id, SimpleXMLElement $xml): SimpleXMLElement
    {
        $attributes = $xml->attributes();
        $unit = new self($db);
        $unit->setParentId($parent_id);
        $unit->setTitle($attributes["title"]->__toString());
        $unit->setValue((int) $attributes["value"]);
        $unit->setPosition((int) $attributes["position"]);
        $unit->create();
        return $xml;
    }

    public function read(): void
    {
        $set = $this->db->query(
            'SELECT * FROM ' . self::TABLE_NAME . ' ' . ' WHERE id = '
            . $this->db->quote($this->getId(), 'integer')
        );
        $set = $this->db->fetchObject($set);
        if (!is_null($set)) {
            $this->setObjectValuesFromRecord($this, ($set));
        }
    }

    final public function initDB(): void
    {
        if (!$this->db->tableExists(self::TABLE_NAME)) {
            $this->db->createTable(self::TABLE_NAME, $this->getArrayForDbWithAttributes());
            $this->db->addPrimaryKey(self::TABLE_NAME, ['id']);
            $this->db->createSequence(self::TABLE_NAME);
        }
    }

    final public function updateDB(): void
    {
        if (!$this->db->tableExists(self::TABLE_NAME)) {
            $this->initDB();
            return;
        }
        foreach ($this->getArrayForDbWithAttributes() as $property => $attributes) {
            if (!$this->db->tableColumnExists(self::TABLE_NAME, $property)) {
                $this->db->addTableColumn(self::TABLE_NAME, $property, $attributes);
            }
        }
    }

    public function create(): void
    {
        if ($this->getId() !== 0) {
            $this->update();

            return;
        }
        $this->setId($this->db->nextId(self::TABLE_NAME));
        $this->db->insert(self::TABLE_NAME, $this->getArrayForDb());
    }

    public function delete(): int
    {
        return $this->db->manipulate('DELETE FROM ' . self::TABLE_NAME . ' WHERE id = ' . $this->getId());
    }

    public function update(): void
    {
        if ($this->getId() === 0) {
            $this->create();

            return;
        }
        $this->db->update(self::TABLE_NAME, $this->getArrayForDb(), $this->getIdForDb());
    }

    /**
     * @return self[]
     */
    public static function _getAllInstancesByParentId(ilDBInterface $db, int $parent_id): array
    {
        $return = [];
        $set = $db->query(
            'SELECT * FROM ' . self::TABLE_NAME . ' ' . ' WHERE parent_id = ' . $parent_id . ' ORDER BY position ASC'
        );
        while ($rec = $db->fetchObject($set)) {
            $return[] = new self($db, $rec->id);
        }

        return $return;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setParentId(int $parent_id): void
    {
        $this->parent_id = $parent_id;
    }

    public function getParentId(): int
    {
        return $this->parent_id;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setValue(int $value): void
    {
        $this->value = $value;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
