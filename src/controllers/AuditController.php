<?php

namespace iceboxind\sorts\controllers;

use Craft;
use craft\web\Controller;
use iceboxind\sorts\jobs\FixEntriesJob;
use iceboxind\sorts\Plugin;
use iceboxind\sorts\utilities\AuditUtility;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Queues the fix for the entries ticked on the audit utility (Pro).
 */
class AuditController extends Controller
{
    /** Most entries one request will queue. */
    private const MAX_ENTRIES = 500;

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $this->requireCpRequest();
        $this->requirePermission('utility:' . AuditUtility::id());
        // The edition is asked here, when the work is queued, never when it runs.
        if (!Plugin::getInstance()->isPro()) {
            throw new ForbiddenHttpException(Craft::t('sorts', 'Fixing stored punctuation needs Sorts Pro.'));
        }
        return true;
    }

    public function actionFix(): Response
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();

        $ticked = $request->getBodyParam('entries');
        $entries = [];
        foreach (is_array($ticked) ? $ticked : [] as $item) {
            // Each is "entryId:siteId". Anything else is dropped.
            if (is_string($item) && preg_match('/^(\d{1,10}):(\d{1,10})$/', $item, $m)) {
                $entries[$item] = [(int) $m[1], (int) $m[2]];
            }
        }
        $entries = array_slice(array_values($entries), 0, self::MAX_ENTRIES);
        if (!$entries) {
            $this->setFailFlash(Craft::t('sorts', 'Tick at least one entry to fix.'));
            return $this->redirectToPostedUrl();
        }

        $createRevisions = $request->getBodyParam('createRevisions') === '1';
        FixEntriesJob::enqueue($entries, $createRevisions, (int) Craft::$app->getUser()->getId());
        $this->setSuccessFlash(Craft::t('sorts', 'Queued a fix for {count, number} {count, plural, =1{entry} other{entries}}.', ['count' => count($entries)]));
        return $this->redirectToPostedUrl();
    }
}
