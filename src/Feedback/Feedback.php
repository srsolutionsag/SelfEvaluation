<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\Feedback;

use ilDBInterface;
use SimpleXMLElement;
use ilub\plugin\SelfEvaluation\DatabaseHelper\ArrayForDB;
use ilub\plugin\SelfEvaluation\DatabaseHelper\hasDBFields;

class Feedback implements hasDBFields
{
    use ArrayForDB;

    public const TABLE_NAME = 'rep_robj_xsev_fb';
    protected int $parent_id = 0;
    protected string $title = '';
    protected string $description = '';
    protected int $start_value = 0;
    protected int $end_value = 100;
    protected string $feedback_text = '';
    protected bool $parent_type_overall = false;

    public function __construct(protected ilDBInterface $db, public int $id = 0)
    {
        if ($this->id !== 0) {
            $this->read();
        }
    }

    public function cloneTo(int $parent_id): Feedback
    {
        $clone = new self($this->db);
        $clone->setParentId($parent_id);
        $clone->setTitle($this->getTitle());
        $clone->setDescription($this->getDescription());
        $clone->setStartValue($this->getStartValue());
        $clone->setEndValue($this->getEndValue());
        $clone->setFeedbackText($this->getFeedbackText());
        $clone->setParentTypeOverall($this->isParentTypeOverall());
        $clone->update();
        return $clone;
    }

    public function toXml(SimpleXMLElement $xml): SimpleXMLElement
    {
        $child_xml = $xml->addChild("feedback");
        $child_xml->addAttribute("parentId", (string) $this->getParentId());
        $child_xml->addAttribute("title", $this->getTitle());
        $child_xml->addAttribute("description", $this->getDescription());
        $child_xml->addAttribute("startValue", (string) $this->getStartValue());
        $child_xml->addAttribute("endValue", (string) $this->getEndValue());
        $child_xml->addAttribute("feedbackText", $this->getFeedbackText());
        $child_xml->addAttribute("parentTypeOverall", (string) $this->isParentTypeOverall());

        return $xml;
    }

    public static function fromXml(ilDBInterface $db, int $parent_id, SimpleXMLElement $xml): SimpleXMLElement
    {
        $attributes = $xml->attributes();
        $question = new self($db);
        $question->setParentId($parent_id);
        $question->setTitle($attributes["title"]->__toString());
        $question->setDescription($attributes["description"]->__toString());
        $question->setStartValue((int) $attributes["startValue"]);
        $question->setEndValue((int) $attributes["endValue"]);
        $question->setFeedbackText($attributes["feedbackText"]->__toString());
        $question->setParentTypeOverall((bool) $attributes["parentTypeOverall"]);
        $question->create();
        return $xml;
    }

    public function read(): void
    {
        $set = $this->db->query(
            'SELECT * FROM ' . self::TABLE_NAME . ' ' . ' WHERE id = '
            . $this->db->quote($this->getId(), 'integer')
        );

        $this->setObjectValuesFromRecord($this, $this->db->fetchObject($set));
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
    public static function _getAllInstancesForParentId(
        ilDBInterface $db,
        int $parent_id,
        bool $as_array = false,
        bool $is_overall = false
    ): array {
        $return = [];
        $q = 'SELECT * FROM ' . self::TABLE_NAME . ' ' .
            ' WHERE parent_id = ' . $db->quote($parent_id, 'integer');

        if ($is_overall) {
            $q .= ' AND parent_type_overall = ' . $db->quote($is_overall, 'integer');
        }
        $q .= ' ORDER BY start_value ASC';

        $set = $db->query($q);

        while ($rec = $db->fetchObject($set)) {
            $feedback = new self($db);
            $feedback->setObjectValuesFromRecord($feedback, $rec);

            $return[] = $as_array ? $feedback->getArray() : $feedback;
        }

        return $return;
    }

    /**
     * @return self[]
     */
    public static function _getAllInstances(ilDBInterface $db, bool $is_overall = false): array
    {
        $return = [];
        $q = 'SELECT * FROM ' . self::TABLE_NAME . ' ';

        if ($is_overall) {
            $q .= ' WHERE parent_type_overall = ' . $db->quote($is_overall, 'integer');
        }
        $set = $db->query($q);

        while ($rec = $db->fetchObject($set)) {
            $feedback = new self($db);
            $feedback->setObjectValuesFromRecord($feedback, $rec);
            $return[] = $feedback;
        }

        return $return;
    }

    public static function _getFeedbackForPercentage(
        ilDBInterface $db,
        int $parent_id,
        float $percentage,
        bool $is_overall = false
    ): ?Feedback {
        $q = 'SELECT id FROM ' . self::TABLE_NAME . ' ' . ' WHERE parent_id = ' . $db->quote($parent_id, 'integer')
            . ' AND start_value <= ' . $db->quote($percentage, 'float')
            . ' AND end_value >= ' . $db->quote($percentage, 'float');
        if ($is_overall) {
            $q .= ' AND parent_type_overall = ' . $db->quote($is_overall, 'integer');
        }
        $set = $db->query($q);

        while ($rec = $db->fetchObject($set)) {
            return new self($db, (int) $rec->id);
        }
        return null;
    }

    public static function _getNextMinValueForParentId(
        ilDBInterface $db,
        int $parent_id,
        int $value = 0,
        int $ignore = 0,
        bool $is_overall = false
    ): int {
        for ($return = $value; $return < 100; $return++) {
            $q =
                'SELECT id FROM ' . self::TABLE_NAME . ' ' . ' WHERE parent_id = ' . $db->quote($parent_id, 'integer')
                . ' AND start_value <= ' . $db->quote($return, 'integer')
                . ' AND end_value > ' . $db->quote($return, 'integer');
            if ($ignore !== 0) {
                $q .= ' AND id != ' . $db->quote($ignore, 'integer');
            }
            if ($is_overall) {
                $q .= ' AND parent_type_overall = ' . $db->quote($is_overall, 'integer');
            }
            $set = $db->query($q);
            $res = $db->fetchObject($set);
            if (is_null($res)) {
                return $return;
            }
        }

        return 100;
    }

    public static function _getNextMaxValueForParentId(
        ilDBInterface $db,
        int $parent_id,
        int $value = 0,
        int $ignore = 0,
        bool $is_overall = false
    ): int {
        for ($return = $value + 1; $return <= 100; $return++) {
            $q =
                'SELECT id FROM ' . self::TABLE_NAME . ' ' . ' WHERE parent_id = ' . $db->quote($parent_id, 'integer')
                . ' AND start_value <= ' . $db->quote($return, 'integer')
                . ' AND end_value >= ' . $db->quote($return, 'integer');
            if ($ignore !== 0) {
                $q .= ' AND id != ' . $db->quote($ignore, 'integer');
            }
            if ($is_overall) {
                $q .= ' AND parent_type_overall = ' . $db->quote($is_overall, 'integer');
            }
            $set = $db->query($q);
            $res = $db->fetchObject($set);
            if ($res && $res->id) {
                return $return;
            }
        }

        return 100;
    }

    public static function _isComplete(ilDBInterface $db, int $parent_id, bool $is_overall = false): bool
    {
        $min = self::_getNextMinValueForParentId($db, $parent_id, 0, 0, $is_overall);
        $max = self::_getNextMaxValueForParentId($db, $parent_id, $min, 0, $is_overall);

        return $min === 100 && $max === 100;
    }

    public static function _getNewInstanceByParentId(ilDBInterface $db, int $parent_id, bool $is_overall = false): self
    {
        $obj = new self($db);
        $obj->setParentId($parent_id);
        $obj->setParentTypeOverall($is_overall);

        return $obj;
    }

    public static function _rearangeFeedbackLinear(ilDBInterface $db, int $parent_id, bool $is_overall = false): int
    {
        $obj = new self($db);
        $obj->setParentId($parent_id);

        $feedbacks = self::_getAllInstancesForParentId($db, $parent_id, false, $is_overall);
        $nr_feedbacks = count($feedbacks) + 1;
        $range_per_feedback = (int) floor(100 / $nr_feedbacks);
        $remainder = 100 - $range_per_feedback * $nr_feedbacks;

        $start = 0;
        foreach ($feedbacks as $feedback) {
            $range = $range_per_feedback;
            if ($remainder > 0) {
                $range++;
                $remainder -= 1;
            }
            $feedback->setStartValue($start);
            $end = $start + $range;
            $feedback->setEndValue($end);
            $feedback->update();
            $start = $end;
        }

        return $range_per_feedback;
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

    public function setEndValue(int $end_value): void
    {
        $this->end_value = $end_value;
    }

    public function getEndValue(): int
    {
        return $this->end_value;
    }

    public function setFeedbackText(string $feedback_text): void
    {
        $this->feedback_text = $feedback_text;
    }

    public function getFeedbackText(): string
    {
        return $this->feedback_text;
    }

    public function setParentId(int $parent_id): void
    {
        $this->parent_id = $parent_id;
    }

    public function getParentId(): int
    {
        return $this->parent_id;
    }

    public function setStartValue(int $start_value): void
    {
        $this->start_value = $start_value;
    }

    public function getStartValue(): int
    {
        return $this->start_value;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function isParentTypeOverall(): bool
    {
        return $this->parent_type_overall;
    }

    public function setParentTypeOverall(bool $parent_type_overall): void
    {
        $this->parent_type_overall = $parent_type_overall;
    }
}
