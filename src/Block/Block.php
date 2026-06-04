<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\Block;

use ilub\plugin\SelfEvaluation\DatabaseHelper\ArrayForDB;
use ilDBInterface;
use ilub\plugin\SelfEvaluation\DatabaseHelper\hasDBFields;
use SimpleXMLElement;
use ilCtrl;
use ilSelfEvaluationPlugin;
use ilub\plugin\SelfEvaluation\Question\Question as BaseQuestion;
use ilub\plugin\SelfEvaluation\Identity\Identity;

abstract class Block implements hasDBFields, BlockType
{
    use ArrayForDB;

    protected string $title = '';
    protected string $description = '';
    protected int $position = 99;
    protected int $parent_id = 0;

    public function __construct(protected ilDBInterface $db, public int $id = 0)
    {
        if ($this->id !== 0) {
            $this->read();
        }
    }

    abstract public function cloneTo(int $parent_id);

    abstract public function toXml(SimpleXMLElement $xml): SimpleXMLElement;

    abstract public static function fromXml(ilDBInterface $db, int $parent_id, SimpleXMLElement $xml): SimpleXMLElement;

    /**
     * @return BaseQuestion[]
     */
    abstract public function getQuestions(): array;

    public function read(): void
    {
        $set = $this->db->query(
            'SELECT * FROM ' . static::_getTableName() . ' ' . ' WHERE id = '
            . $this->db->quote($this->getId(), 'integer')
        );
        $this->setObjectValuesFromRecord($this, $this->db->fetchObject($set));
    }

    abstract public static function _getTableName(): string;

    public function initDB(): void
    {
        if (!$this->db->tableExists(static::_getTableName())) {
            $this->db->createTable(static::_getTableName(), $this->getArrayForDbWithAttributes());
            $this->db->addPrimaryKey(static::_getTableName(), ['id']);
            $this->db->createSequence(static::_getTableName());
        }
    }

    final public function updateDB(): void
    {
        if (!$this->db->tableExists(static::_getTableName())) {
            $this->initDB();

            return;
        }
        foreach ($this->getArrayForDbWithAttributes() as $property => $attributes) {
            if (!$this->db->tableColumnExists(static::_getTableName(), $property)) {
                $this->db->addTableColumn(static::_getTableName(), $property, $attributes);
            }
        }
    }

    public function create(): void
    {
        $this->setId($this->db->nextId(static::_getTableName()));
        $this->setPosition(BlockFactory::_getNextPositionAcrossBlocks($this->db, $this->getParentId()));
        $this->db->insert(static::_getTableName(), $this->getArrayForDb());
    }

    public function delete(): int
    {
        return $this->db->manipulate(
            'DELETE FROM ' . static::_getTableName() . ' WHERE id = '
            . $this->db->quote($this->getId(), 'integer')
        );
    }

    public function update(): void
    {
        if ($this->getId() === 0) {
            $this->create();

            return;
        }
        $this->db->update(static::_getTableName(), $this->getArrayForDb(), $this->getIdForDb());
    }

    /**
     * @return Block[]
     */
    public static function _getAllInstancesByParentId(ilDBInterface $db, int $parent_id): array
    {
        $return = [];
        $set = $db->query(
            'SELECT * FROM ' . static::_getTableName(
            ) . ' ' . ' WHERE parent_id = ' . $parent_id . ' ORDER BY position ASC'
        );
        while ($rec = $db->fetchObject($set)) {
            $block = new static($db);
            $block->setObjectValuesFromRecord($block, $rec);
            $return[] = $block;
        }

        return $return;
    }

    /**
     * @return static[]
     */
    public static function _getAllInstancesByIdentifierId(ilDBInterface $db, string $identity_id): array
    {
        return self::_getAllInstancesByParentId($db, Identity::_getObjIdForIdentityId($db, $identity_id));
    }

    public function getNextPosition(int $parent_id): int
    {
        $set = $this->db->query(
            'SELECT MAX(position) next_pos FROM ' . static::_getTableName() . ' ' . ' WHERE parent_id = '
            . $this->db->quote($parent_id, 'integer')
        );
        while ($rec = $this->db->fetchObject($set)) {
            return $rec->next_pos + 1;
        }

        return 1;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setParentId(int $parent_id): void
    {
        $this->parent_id = $parent_id;
    }

    public function getParentId(): int
    {
        return $this->parent_id;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPositionId(): string
    {
        return static::class . '_' . $this->getId();
    }

    abstract public function getBlockTableRow(
        ilDBInterface $db,
        ilCtrl $ilCtrl,
        ilSelfEvaluationPlugin $plugin
    ): BlockTableRow;

    public function unserialize($serialized): Block
    {
        global $DIC;

        $this->db = $DIC->database();
        return $this->fromArray(unserialize($serialized));
    }
}
