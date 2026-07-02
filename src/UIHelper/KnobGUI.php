<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\UIHelper;

use ilRepositoryObjectPlugin;
use ilGlobalTemplateInterface;

class KnobGUI
{
    public const CAP_BUTT = '\'butt\'';
    public const CAP_ROUND = '\'round\'';
    public const CAP_GAUGE = '\'gauge\'';

    private static int $num = 1;
    protected string $html = '';
    protected int $value = 0;
    protected int $min = 0;
    protected int $max = 100;
    protected array $fg_color = [208, 232, 255];
    protected array $input_color = [208, 232, 255];
    protected array $bg_color = [240, 240, 240];
    protected bool $read_only = true;
    protected int $angle_offset = 0;
    protected int $angle_arc = 360;
    protected bool $stopper = true;
    protected float $thickness = 0.3;
    protected string $line_cap = self::CAP_BUTT;
    protected int $height = 50;
    protected bool $display_input = true;
    protected bool $display_previous = false;

    public function render(ilGlobalTemplateInterface $tpl, ilRepositoryObjectPlugin $plugin): void
    {
        self::$num++;
        global $DIC;
        $maximum = max(1, $this->getMax());
        $main = min($maximum, max($this->getMin(), $this->getValue()));
        $progress_meter = $DIC->ui()->factory()->chart()->progressMeter()->standard($maximum, $main);
        $this->setHtml('<div class="knob">' . $DIC->ui()->renderer()->render($progress_meter) . '</div>');
    }

    public function setHtml(string $html): void
    {
        $this->html = $html;
    }

    public function getHtml(ilGlobalTemplateInterface $tpl, ilRepositoryObjectPlugin $plugin): string
    {
        $this->render($tpl, $plugin);

        return $this->html;
    }

    public function setMax(int $max): void
    {
        $this->max = $max;
    }

    public function getMax(): int
    {
        return $this->max;
    }

    public function setMin(int $min): void
    {
        $this->min = $min;
    }

    public function getMin(): int
    {
        return $this->min;
    }

    public static function setNum(int $num): void
    {
        self::$num = $num;
    }

    public static function getNum(): int
    {
        return self::$num;
    }

    public function setValue(int $value): void
    {
        $this->value = $value;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function setFgColor(array $fg_color): void
    {
        $this->fg_color = $fg_color;
    }

    public function getFgColor(): array
    {
        return $this->fg_color;
    }

    public function setInputColor(array $in_color): void
    {
        $this->input_color = $in_color;
    }

    public function getInputColor(): array
    {
        return $this->input_color;
    }

    public function setReadOnly(bool $read_only): void
    {
        $this->read_only = $read_only;
    }

    public function getReadOnly(): bool
    {
        return $this->read_only;
    }

    public function setAngleArc(int $angle_arc): void
    {
        $this->angle_arc = $angle_arc;
    }

    public function getAngleArc(): int
    {
        return $this->angle_arc;
    }

    public function setAngleOffset(int $angle_offset): void
    {
        $this->angle_offset = $angle_offset;
    }

    public function getAngleOffset(): int
    {
        return $this->angle_offset;
    }

    public function setBgColor(array $bg_color): void
    {
        $this->bg_color = $bg_color;
    }

    public function getBgColor(): array
    {
        return $this->bg_color;
    }

    public function setDisplayInput(bool $display_input): void
    {
        $this->display_input = $display_input;
    }

    public function getDisplayInput(): bool
    {
        return $this->display_input;
    }

    public function setDisplayPrevious(bool $display_previous): void
    {
        $this->display_previous = $display_previous;
    }

    public function getDisplayPrevious(): bool
    {
        return $this->display_previous;
    }

    public function setLineCap(string $line_cap): void
    {
        $this->line_cap = $line_cap;
    }

    public function getLineCap(): string
    {
        return $this->line_cap;
    }

    public function setStopper(bool $stopper): void
    {
        $this->stopper = $stopper;
    }

    public function getStopper(): bool
    {
        return $this->stopper;
    }

    public function setThickness(float $thickness): void
    {
        $this->thickness = $thickness;
    }

    public function getThickness(): float
    {
        return $this->thickness;
    }

    public function setHeight(int $height): void
    {
        $this->height = $height;
    }

    public function getHeight(): int
    {
        return $this->height;
    }
}
