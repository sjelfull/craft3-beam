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
     * @var string|null The filesystem handle to use for temporary file storage
     * This is useful for load-balanced environments where the local filesystem is not shared
     * If not set, will use the local temp directory
     */
    public ?string $tempFilesystemHandle = null;

    /**
     * @var string|null The subfolder path within the filesystem to use for temp files
     * Defaults to 'beam' if not specified
     */
    public ?string $tempSubfolder = 'beam';

    public function rules(): array
    {
        return [
            [['tempFilesystemHandle', 'tempSubfolder'], 'string'],
        ];
    }
}
