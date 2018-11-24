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

use superbig\beam\Beam;

use Craft;
use craft\base\Model;

/**
 * @author    Superbig
 * @package   Beam
 * @since     2.0.0
 */
class BeamModel extends Model
{
    // Public Properties
    // =========================================================================

    /**  @var array */
    public $header = [];

    /**  @var array */
    public $content = [];

    /**  @var array */
    public $rows = [];

    /**  @var string */
    public $filename = 'output';

    /**  @var string */
    public $sheetName = 'Sheet';

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        if (!empty($this->rows)) {
            $this->content = $this->rows;
        }
    }

    /**
     * @param array $content
     *
     * @return $this
     */
    public function append(array $content = [])
    {
        if (isset($content[0]) && !\is_array($content[0])) {
            $content = [$content];
        }

        $this->content = array_merge($this->content, $content);

        return $this;
    }

    public function setHeader($headers = [])
    {
        $this->header = $headers;

        return $this;
    }

    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param $content
     *
     * @return $this
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    public function getConfig()
    {
        return [
            'header' => $this->header,
            'rows'   => $this->content,
        ];
    }

    public function getFilename($ext = null)
    {
        $filename = pathinfo(filter_var($this->filename, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW), PATHINFO_FILENAME);

        return "$filename.$ext";
    }

    public function setFilename($filename = null)
    {
        $this->filename = $filename;

        return $this;
    }

    public function csv($filename = null)
    {
        if ($filename) {
            $this->filename = $filename;
        }

        return Beam::$plugin->beamService->csv($this);
    }

    public function xlsx($filename = null)
    {
        if ($filename) {
            $this->filename = $filename;
        }

        return Beam::$plugin->beamService->xlsx($this);
    }

    public function html()
    {

    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['filename', 'string'],
        ];
    }
}
