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
use craft\helpers\App;

/**
 * @author    Superbig
 * @package   Beam
 * @since     2.0.0
 */
class Settings extends Model
{
    /**
     * Whether to automatically delete temporary files after download.
     *
     * May be a boolean or an environment variable reference (e.g. `$BEAM_DELETE_FILES`).
     *
     * @var bool|string
     */
    public bool|string $deleteFilesAfterDownload = false;

    public function rules(): array
    {
        return [
            [['deleteFilesAfterDownload'], 'default', 'value' => false],
        ];
    }

    /**
     * Returns whether temporary files should be deleted after download.
     */
    public function getDeleteFilesAfterDownload(): bool
    {
        return (bool)App::parseBooleanEnv($this->deleteFilesAfterDownload);
    }
}
