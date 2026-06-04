<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\UIHelper\Scale;

use ilDBInterface;
use ilub\plugin\SelfEvaluation\DatabaseHelper\hasDBFields;
use SimpleXMLElement;
use ilub\plugin\SelfEvaluation\DatabaseHelper\ArrayForDB;

class Scale implements hasDBFields
{
    use ArrayForDB;

    public const TABLE_NAME = 'rep_robj_xsev_scale';
    protected int $parent_id = 0;

    /**
     * @var ScaleUnit[]
     */
    protected array $units;

    public function __construct(protected ilDBInterface $db, protected int $id = 0)
    {
        if ($this->id !== 0) {
            $this->read();
        }
        $this->units = ScaleUnit::_getAllInstancesByParentId($this->db, $this->getId());
    }

    public function cloneTo(int $parent_obj_id): Scale
    {
        $clone = new self($this->db);
        $clone->setParentId($parent_obj_id);
        $clone->update();
        $old_units = ScaleUnit::_getAllInstancesByParentId($this->db, $this->getId());
        $new_units = [];

        foreach ($old_units as $old_unit) {
            $new_units[] = $old_unit->cloneTo($clone->getId());
        }
        $clone->units = $new_units;

        return $clone;
    }

    public function toXml(SimpleXMLElement $xml): SimpleXMLElement
    {
        $child_xml = $xml->addChild("scale");
        $units = ScaleUnit::_getAllInstancesByParentId($this->db, $this->getId());

        foreach ($units as $unit) {
            $child_xml = $unit->toXml($this->getId(), $child_xml);
        }

        return $xml;
    }

    public static function fromXml(ilDBInterface $db, int $parent_id, SimpleXMLElement $xml): SimpleXMLElement
    {
        $scale = new self($db);
        $scale->setParentId($parent_id);
        $scale->create();

        foreach ($xml->scaleUnit as $unit) {
            ScaleUnit::fromXml($db, $scale->getId(), $unit);
        }

        return $xml;
    }

    /**
     * @return array (unit value => unit title)
     */
    public function getUnitsAsArray(bool $flipped = false): array
    {
        $return = [];
        foreach ($this->units as $k => $u) {
            if ($flipped) {
                $return[$this->units[count($this->units) - $k - 1]->getValue()] = $u->getTitle();
            } else {
                $return[$u->getValue()] = $u->getTitle();
            }
        }

        return $return;
    }

    public function hasUnits(): bool
    {
        return count($this->units) > 0;
    }

    public function getUnitsAsRelativeArray(): array
    {
        $return = [];
        $min_max = $this->getMinMaxValue();
        $max = $min_max['max'];
        if ($max != 0) {
            foreach ($this->units as $u) {
                $return[(int) ($u->getValue() * 100 / $max)] = $u->getTitle() . ' (' . $u->getValue() . ')';
            }
        } else {
            foreach ($this->units as $u) {
                $return[$u->getValue()] = $u->getTitle() . ' (' . $u->getValue() . ')';
            }
        }

        return $return;
    }

    /**
     * @return ScaleUnit[]
     */
    public function getUnits(): array
    {
        return $this->units;
    }

    public function getMinMaxValue(): array
    {
        $min = 999999;
        $max = 0;
        foreach ($this->units as $u) {
            if ($u->getValue() > $max) {
                $max = $u->getValue();
            }
            if ($u->getValue() < $min) {
                $min = $u->getValue();
            }
        }

        return ['min' => $min, 'max' => $max];
    }

    public function read(): void
    {
        $set = $this->db->query('SELECT * FROM ' . self::TABLE_NAME . ' ' . ' WHERE id = ' . $this->getId());
        $this->setObjectValuesFromRecord($this, $this->db->fetchObject($set));
    }

    protected function getNonDbFields(): array
    {
        return ['db', 'units'];
    }

    final public function initDB(): void
    {
        if (!$this->db->tableExists(self::TABLE_NAME)) {
            $this->db->createTable(self::TABLE_NAME, $this->getArrayForDbWithAttributes());
            $this->db->addPrimaryKey(self::TABLE_NAME, ['id']);
            $this->db->createSequence(self::TABLE_NAME);
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

    public function delete(): void
    {
        $this->db->manipulate('DELETE FROM ' . self::TABLE_NAME . ' WHERE id = ' . $this->getId());
    }

    public function update(): void
    {
        if ($this->getId() === 0) {
            $this->create();
        }
        $this->db->update(self::TABLE_NAME, $this->getArrayForDb(), $this->getIdForDb());
    }

    public static function _getInstanceByObjId(ilDBInterface $db, int $parent_obj_id): self
    {
        $set = $db->query("SELECT * FROM " . self::TABLE_NAME . " " . " WHERE parent_id = " . $parent_obj_id);
        while ($rec = $db->fetchObject($set)) {
            return new self($db, (int) $rec->id);
        }
        $obj = new self($db);
        $obj->setParentId($parent_obj_id);

        return $obj;
    }

    public static function _getHighestScaleByObjId(ilDBInterface $db, int $parent_obj_id): int
    {
        $scale = self::_getInstanceByObjId($db, $parent_obj_id)->getUnitsAsArray();
        $sorted_scale = array_keys($scale);
        sort($sorted_scale);
        return $sorted_scale[count($sorted_scale) - 1];
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAmount(): int
    {
        return count($this->units);
    }

    public function setParentId(int $parent_id): void
    {
        $this->parent_id = $parent_id;
    }

    public function getParentId(): int
    {
        return $this->parent_id;
    }
}
