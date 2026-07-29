<?php

declare(strict_types=1);

namespace app\plugins\spd_layout;

use app\components\UrlHelper;
use app\controllers\UserController as UpstreamUserController;
use app\models\db\User;
use app\models\http\{HtmlResponse, RedirectResponse, ResponseInterface};
use app\models\settings\AntragsgruenApp;

/**
 * Makes password recovery truthful without changing the upstream user model.
 *
 * Other user actions remain inherited. Recovery is forced through synchronous
 * SMTP so the success notice means the provider accepted the message. A
 * disabled transport is rejected before a token is created. For a configured
 * transport, the recovery row is locked for the complete upstream send path so
 * a failed delivery can roll back its token/rate-limit state without racing a
 * concurrent recovery request for the same account.
 */
class RecoveryController extends UpstreamUserController
{
    /** Recovery is the only upstream user route replaced by this plugin. */
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
                \Yii::t('base', 'err_email_not_sent') .
                ': Der Versand ist derzeit nicht verfügbar. Es wurde kein ' .
                'Wiederherstellungs-Code erstellt. Bitte versuche es später erneut.'
            );

            return new RedirectResponse(UrlHelper::createUrl('user/recovery'));
        }

        $requestedEmail = (string) $this->getRequestValue('email', '');
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
            try {
                $tableName = User::getDb()->quoteTableName(User::tableName());
                $lockedUserId = User::getDb()->createCommand(
                    "SELECT id FROM $tableName WHERE id = :id FOR UPDATE"
                )->bindValue(':id', $user->id)->queryScalar();
                if ((int) $lockedUserId !== $user->id) {
                    throw new \RuntimeException('recovery user disappeared while acquiring its lock');
                }

                $response = parent::actionRecovery($email, $code);
                if ($this->responseHasMailFailure($response)) {
                    $transaction->rollBack();
                } else {
                    $transaction->commit();
                }
            } catch (\Throwable $e) {
                if ($transaction->getIsActive()) {
                    $transaction->rollBack();
                }
                throw $e;
            }
        } finally {
            $params->backgroundJobs = $previousBackgroundJobs;
        }

        return $response;
    }

    private function responseHasMailFailure(ResponseInterface $response): bool
    {
        if (!$response instanceof HtmlResponse) {
            return false;
        }

        $text = html_entity_decode(
            strip_tags($response->getHtml()),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        return str_contains($text, \Yii::t('base', 'err_email_not_sent'));
    }
}
