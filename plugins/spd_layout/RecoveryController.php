<?php

declare(strict_types=1);

namespace app\plugins\spd_layout;

use app\components\UrlHelper;
use app\controllers\UserController as UpstreamUserController;
use app\models\db\User;
use app\models\http\{RedirectResponse, ResponseInterface};
use app\models\settings\AntragsgruenApp;

/**
 * Makes password recovery truthful without changing the upstream user model.
 *
 * Recovery is the only replaced user route. A disabled transport is rejected
 * before a token is created. With a configured transport, the user row is
 * locked for the upstream send path and failed delivery rolls back the token
 * and rate-limit state.
 */
class RecoveryController extends UpstreamUserController
{
    public $enableCsrfValidation = true;

    public function actionRecovery(string $email = '', string $code = ''): ResponseInterface
    {
        if (!$this->isPostSet('send')) {
            return parent::actionRecovery($email, $code);
        }

        $params = AntragsgruenApp::getInstance();
        if (($params->mailService['transport'] ?? null) === 'none') {
            $this->getHttpSession()->setFlash(
                'error',
                \Yii::t('base', 'err_email_not_sent') . ': ' .
                \Yii::t('spd_layout', 'recovery_mail_disabled')
            );

            return new RedirectResponse(UrlHelper::createUrl('user/recovery'));
        }

        $requestedEmail = (string)$this->getRequestValue('email', '');
        $user = User::findOne(['auth' => 'email:' . $requestedEmail]);
        $previousBackgroundJobs = $params->backgroundJobs;
        $params->backgroundJobs = array_merge(
            $params->backgroundJobs ?? [],
            ['notifications' => false]
        );

        try {
            if ($user === null) {
                return parent::actionRecovery($email, $code);
            }

            $transaction = User::getDb()->beginTransaction();
            // A flash from a previous request must not be mistaken for the
            // transport result of this request. Consume it for the duration
            // of the upstream call, then restore it only after a successful
            // commit. This keeps rollback tied to a newly reported failure.
            $previousErrorFlash = $this->getHttpSession()->getFlash('error', null, true);
            $restorePreviousErrorFlash = false;
            try {
                $tableName = User::getDb()->quoteTableName(User::tableName());
                $lockedUserId = User::getDb()->createCommand(
                    "SELECT id FROM $tableName WHERE id = :id FOR UPDATE"
                )->bindValue(':id', $user->id)->queryScalar();
                if ((int)$lockedUserId !== (int)$user->id) {
                    throw new \RuntimeException('recovery user disappeared while acquiring its lock');
                }

                $response = parent::actionRecovery($email, $code);
                if ($this->sessionHasMailFailure()) {
                    $transaction->rollBack();
                } else {
                    $transaction->commit();
                    $restorePreviousErrorFlash = $previousErrorFlash !== null;
                }
            } catch (\Throwable $e) {
                if ($transaction->getIsActive()) {
                    $transaction->rollBack();
                }
                $restorePreviousErrorFlash = $previousErrorFlash !== null;
                throw $e;
            } finally {
                if ($restorePreviousErrorFlash) {
                    $this->getHttpSession()->setFlash('error', $previousErrorFlash);
                }
            }
        } finally {
            $params->backgroundJobs = $previousBackgroundJobs;
        }

        return $response;
    }

    /**
     * Native recovery reports transport failures through the session flash.
     * Inspecting that flash avoids parsing translated HTML, while preserving
     * the transaction rollback around the upstream send path.
     */
    private function sessionHasMailFailure(): bool
    {
        $flash = $this->getHttpSession()->getFlash('error', null, false);

        return RecoveryDeliveryResult::reportsFailure(
            $flash,
            (string)\Yii::t('base', 'err_email_not_sent')
        );
    }
}
