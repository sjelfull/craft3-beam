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
    public array $sheets = [];
    private ?string $activeSheet = null;

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

        if ($this->activeSheet !== null) {
            // Append to the active sheet
            $this->ensureSheetExists($this->activeSheet);
            $sheetIndex = $this->getSheetIndex($this->activeSheet);
            $this->sheets[$sheetIndex]['content'] = array_merge(
                $this->sheets[$sheetIndex]['content'] ?? [],
                $content
            );
        } else {
            // Append to default content (backward compatibility)
            $this->content = array_merge($this->content, $content);
        }

        return $this;
    }

    public function setHeader(array $headers = []): static
    {
        if ($this->activeSheet !== null) {
            // Set header for the active sheet
            $this->ensureSheetExists($this->activeSheet);
            $sheetIndex = $this->getSheetIndex($this->activeSheet);
            $this->sheets[$sheetIndex]['header'] = $headers;
        } else {
            // Set default header (backward compatibility)
            $this->header = $headers;
        }

        return $this;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function setContent(array $content): static
    {
        if ($this->activeSheet !== null) {
            // Set content for the active sheet
            $this->ensureSheetExists($this->activeSheet);
            $sheetIndex = $this->getSheetIndex($this->activeSheet);
            $this->sheets[$sheetIndex]['content'] = $content;
        } else {
            // Set default content (backward compatibility)
            $this->content = $content;
        }

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

    public function setSheets(array $sheets = []): static
    {
        $this->sheets = $sheets;

        return $this;
    }

    public function sheet(string $name, array $options = []): static
    {
        $this->ensureSheetExists($name);
        $this->activeSheet = $name;

        // Apply options if provided
        if (!empty($options)) {
            $sheetIndex = $this->getSheetIndex($name);
            if (isset($options['header'])) {
                $this->sheets[$sheetIndex]['header'] = $options['header'];
            }
            if (isset($options['content'])) {
                $this->sheets[$sheetIndex]['content'] = $options['content'];
            }
        }

        return $this;
    }

    public function setSheet(string $name): static
    {
        $this->ensureSheetExists($name);
        $this->activeSheet = $name;

        return $this;
    }

    private function ensureSheetExists(string $name): void
    {
        // Check if sheet already exists
        foreach ($this->sheets as $sheet) {
            if (($sheet['name'] ?? '') === $name) {
                return;
            }
        }

        // Create new sheet
        $this->sheets[] = [
            'name' => $name,
            'header' => [],
            'content' => [],
        ];
    }

    private function getSheetIndex(string $name): ?int
    {
        foreach ($this->sheets as $index => $sheet) {
            if (($sheet['name'] ?? '') === $name) {
                return $index;
            }
        }

        return null;
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
