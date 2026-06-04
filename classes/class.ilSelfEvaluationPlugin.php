<?php

declare(strict_types=1);

class ilSelfEvaluationPlugin extends ilRepositoryObjectPlugin
{
    public function __construct()
    {
        global $DIC;
        $this->db = $DIC->database();

        parent::__construct($this->db, $DIC["component.repository"], 'xsev');
    }


    #[\Override]
    public function getPluginName(): string
    {
        return 'SelfEvaluation';
    }

    public function getConfigObject(): ilSelfEvaluationConfig
    {
        return new ilSelfEvaluationConfig($this->getConfigTableName());
    }

    public function getConfigTableName(): string
    {
        return 'rep_robj_xsev_c';
    }

    protected function uninstallCustom(): void
    {
    }

    #[\Override]
    public function allowCopy(): bool
    {
        return true;
    }

    /**
     * @description This is the easiest way to fix all locations which use a template.
     */
    #[\Override]
    public function getTemplate(string $a_template, bool $a_par1 = true, bool $a_par2 = true): ilTemplate
    {
        return new ilTemplate(
            $this->getTemplatePath($a_template),
            $a_par1,
            $a_par2,
            __DIR__ . '/../'
        );
    }

    public function getTemplatePath(string $a_template, bool $relative_from_customizing = false): string
    {
        // remove a leading "default/" if it exists
        if (str_starts_with($a_template, 'default/')) {
            $a_template = substr($a_template, 8);
        }

        if ($relative_from_customizing) {
            $str = __DIR__ . '/../templates/default/' . $a_template;
            // cut everything before "Customizing/"
            $pos = strpos($str, '/Customizing/');
            if ($pos !== false) {
                return '.' . substr($str, $pos);
            }

            return $str;
        }

        return $a_template;
    }

    #[\Override]
    public function getRelativeDirectory(): string
    {
        $ansolute_path = realpath(__DIR__ . '/../');
        // cut everything before /Customizing/
        $pos = strpos($ansolute_path, '/Customizing/');
        if ($pos !== false) {
            return '.' . substr($ansolute_path, $pos);
        }
        return '';
    }

    #[\Override]
    public function getStyleSheetLocation(string $a_css_file): string
    {
        return $this->getRelativeDirectory() . '/templates/' . $a_css_file;
    }

    /**
     * @deprecated the core method is not working ATM.
     */
    #[\Override]
    public static function _getIcon(string $a_type): string
    {
        return 'Customizing/global/plugins/Services/Repository/RepositoryObject/SelfEvaluation/templates/images/icon_xsev.svg';
    }


}
