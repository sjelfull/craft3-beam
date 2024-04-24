<?php
/**
 * Beam plugin for Craft CMS 3.x
 *
 * Generate CSVs and XLS files in your templates
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\beam\models;

use craft\base\Model;

use superbig\beam\Beam;

/**
 * @author    Superbig
 * @package   Beam
 * @since     2.0.0
 */
class BeamModel extends Model
{
    public array $header = [];
    public array $content = [];
    public array $rows = [];
    public string $filename = 'output';
    public string $sheetName = 'Sheet';

    public function init(): void
    {
        parent::init();

        if (!empty($this->rows)) {
            $this->content = $this->rows;
        }
    }

    public function append(array $content = []): static
    {
        if (isset($content[0]) && !\is_array($content[0])) {
            $content = [$content];
        }

        $this->content = array_merge($this->content, $content);

        return $this;
    }

    public function setHeader(array $headers = []): static
    {
        $this->header = $headers;

        return $this;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function setContent(array $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getConfig(): array
    {
        return [
            'header' => $this->header,
            'rows' => $this->content,
        ];
    }

    public function getFilename($ext = null)
    {
        $filename = pathinfo(filter_var($this->filename, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW), PATHINFO_FILENAME);

        return "$filename.$ext";
    }

    public function setFilename($filename = null): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function csv($filename = null): void
    {
        if ($filename) {
            $this->filename = $filename;
        }

        Beam::$plugin->beamService->csv($this);
    }

    public function xlsx($filename = null): void
    {
        if ($filename) {
            $this->filename = $filename;
        }

        Beam::$plugin->beamService->xlsx($this);
    }

    public function rules(): array
    {
        return [
            ['filename', 'string'],
        ];
    }
}
