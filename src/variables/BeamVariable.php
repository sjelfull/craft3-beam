<?php
/**
 * Beam plugin for Craft CMS 3.x
 *
 * Generate CSVs and XLS files in your templates
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\beam\variables;

use superbig\beam\Beam;

use Craft;

/**
 * @author    Superbig
 * @package   Beam
 * @since     2.0.0
 */
class BeamVariable
{
    // Public Methods
    // =========================================================================

    /**
     * @param array $options
     *
     * @return null
     */
    public function csv ($options = [])
    {
        return Beam::$plugin->beamService->csv($options);
    }

    /**
     * @param array $options
     *
     * @return null
     */
    public function xlsx ($options = [])
    {
        return Beam::$plugin->beamService->xlsx($options);
    }
}
