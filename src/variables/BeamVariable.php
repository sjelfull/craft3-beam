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
    public function create($options = [])
    {
        return Beam::$plugin->beamService->create($options);
    }
}
