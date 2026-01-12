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

/**
 * @author    Superbig
 * @package   Beam
 * @since     5.1.0
 */
class Settings extends Model
{
    /**
     * @var bool Whether to automatically delete temporary files after download
     */
    public bool $deleteFilesAfterDownload = false;

    public function rules(): array
    {
        return [
            ['deleteFilesAfterDownload', 'boolean'],
        ];
    }
}
