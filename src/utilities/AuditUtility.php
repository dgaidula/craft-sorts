<?php

namespace iceboxind\sorts\utilities;

use Craft;
use craft\base\Utility;
use iceboxind\sorts\Plugin;
use iceboxind\sorts\services\AuditScanner;

/**
 * CP → Utilities → Sorts (Pro): scan entries for punctuation Sorts would change, preview
 * each change, and fix the ones ticked through the queue. The scan is a GET page, a batch of
 * entries at a time, so it needs no scripting and any scan can be linked to.
 */
class AuditUtility extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('sorts', 'Sorts');
    }

    public static function id(): string
    {
        return 'sorts';
    }

    public static function icon(): ?string
    {
        return dirname(__DIR__) . '/icon.svg';
    }

    public static function contentHtml(): string
    {
        $request = Craft::$app->getRequest();
        $sections = Craft::$app->getEntries()->getAllSections();
        $sites = Craft::$app->getSites()->getEditableSites();

        $sectionId = $request->getQueryParam('section');
        $sectionId = is_string($sectionId) && ctype_digit($sectionId) ? (int) $sectionId : null;
        $siteId = $request->getQueryParam('site');
        $siteId = is_string($siteId) && ctype_digit($siteId) ? (int) $siteId : null;
        $after = $request->getQueryParam('after');
        $after = is_string($after) && ctype_digit($after) ? (int) $after : 0;

        $editableSiteIds = array_map(static fn ($site) => $site->id, $sites);
        $siteId = in_array($siteId, $editableSiteIds, true) ? $siteId : ($editableSiteIds[0] ?? Craft::$app->getSites()->getPrimarySite()->id);

        $result = null;
        if ($request->getQueryParam('scan') !== null) {
            $result = Plugin::getInstance()->auditScanner->scan($sectionId, $siteId, $after, AuditScanner::BATCH_SIZE);
        }

        return Craft::$app->getView()->renderTemplate('sorts/utility', [
            'sections' => $sections,
            'sites' => $sites,
            'sectionId' => $sectionId,
            'siteId' => $siteId,
            'result' => $result,
            'batchSize' => AuditScanner::BATCH_SIZE,
        ]);
    }
}
