<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\Question\Meta;

use ilub\plugin\SelfEvaluation\Question\Question as BaseQuestion;
use SimpleXMLElement;
use ilDBInterface;

class MetaQuestion extends BaseQuestion
{
    public const TABLE_NAME = 'rep_robj_xsev_mqst';
    public const POSTVAR_PREFIX = 'mqst_';
    public const PRIMARY_KEY = 'id';
    protected int $parent_id;
    protected string $name = "";
    protected string $short_title = "";
    protected int $type_id = 0;
    protected array $values = [];
    protected int $required = 0;

    public function cloneTo(int $parent_id): BaseQuestion
    {
        $clone = new self($this->db);
        $clone->setName($this->getName());
        $clone->setShortTitle($this->getShortTitle());
        $clone->setTypeId($this->getTypeId());
        $clone->setValues($this->getValues());
        $clone->enableRequired($this->isRequired());
        $clone->setPosition($this->getPosition());
        $clone->setParentId($parent_id);
        $clone->update();
        return $clone;
    }

    public function toXml(SimpleXMLElement $xml): SimpleXMLElement
    {
        $child_xml = $xml->addChild("metaQuestion");
        $child_xml->addAttribute("containerId", (string) $this->getParentId());
        $child_xml->addAttribute("name", $this->getName());
        $child_xml->addAttribute("shortTitle", $this->getShortTitle());
        $child_xml->addAttribute("typeId", (string) $this->getTypeId());
        $child_xml->addAttribute("values", serialize($this->getValues()));
        $child_xml->addAttribute("enableRequired", (string) $this->isRequired());
        $child_xml->addAttribute("position", (string) $this->getPosition());
        return $xml;
    }

    public static function fromXml(ilDBInterface $db, int $parent_id, SimpleXMLElement $xml): SimpleXMLElement
    {
        $attributes = $xml->attributes();
        $question = new self($db);
        $question->setParentId($parent_id);
        $question->setName((string) $attributes["name"]);
        $question->setShortTitle((string) $attributes["shortTitle"]);
        $question->setTypeId((int) $attributes["typeId"]);
        $question->setValues(unserialize((string) $attributes["values"]));
        $question->enableRequired($attributes["enableRequired"] == "1" ? 1 : 0);
        $question->setPosition((int) $attributes["position"]);
        $question->update();
        return $xml;
    }

    public function getTypeId(): int
    {
        return $this->type_id;
    }

    public function setTypeId(int $type): void
    {
        $this->type_id = $type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return $this->getName();
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getShortTitle(): string
    {
        return $this->short_title;
    }

    public function setShortTitle(string $short_title): void
    {
        $this->short_title = $short_title;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function setValues(array $values): void
    {
        $this->values = $values;
    }

    public function isRequired(): ?int
    {
        return $this->required;
    }

    public function enableRequired(int $status): void
    {
        $this->required = $status;
    }

    /**
     * @return MetaQuestion[]
     */
    public static function _getAllInstancesForParentId(ilDBInterface $db, int $parent_id): array
    {
        $questions = [];
        $stmt = self::_getAllInstancesForParentIdQuery($db, $parent_id);
        while ($rec = $db->fetchObject($stmt)) {
            if (($rec->id ?? $rec->field_id ?? null) === null) {
                continue; // skip empty records
            }
            $question = new self($db);
            $question->setId((int) ($rec->id ?? $rec->field_id));
            $question->setParentId((int) $rec->parent_id);
            $question->setName((string) $rec->name);
            $question->setShortTitle((string) $rec->short_title);
            $question->setTypeId((int) $rec->type_id);
            $question->setValues((array) unserialize($rec->values));
            $question->enableRequired((int) $rec->required);
            $question->setPosition((int) $rec->position);
            $questions[$question->getId()] = $question;
        }
        return $questions;
    }

    public static function _getAllInstancesForParentIdAsArray(ilDBInterface $db, int $parent_id): array
    {
        $questions = [];

        foreach (self::_getAllInstancesForParentId($db, $parent_id) as $question) {
            $questions[] = $question->getArray();
        }

        return $questions;
    }
}
