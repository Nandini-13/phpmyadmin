<?php

declare(strict_types=1);

namespace PhpMyAdmin\Controllers\Preferences;

use PhpMyAdmin\Config;
use PhpMyAdmin\Config\ConfigFile;
use PhpMyAdmin\Config\Forms\User\NaviForm;
use PhpMyAdmin\Controllers\AbstractController;
use PhpMyAdmin\Core;
use PhpMyAdmin\Relation;
use PhpMyAdmin\Response;
use PhpMyAdmin\Template;
use PhpMyAdmin\TwoFactor;
use PhpMyAdmin\Url;
use PhpMyAdmin\UserPreferences;

use function define;
use function ltrim;

class NavigationController extends AbstractController
{
    /** @var UserPreferences */
    private $userPreferences;

    /** @var Relation */
    private $relation;

    /** @var Config */
    private $config;

    /**
     * @param Response $response
     */
    public function __construct(
        $response,
        Template $template,
        UserPreferences $userPreferences,
        Relation $relation,
        Config $config
    ) {
        parent::__construct($response, $template);
        $this->userPreferences = $userPreferences;
        $this->relation = $relation;
        $this->config = $config;
    }

    public function index(): void
    {
        global $cfg, $cf, $error, $tabHash, $hash, $server, $route;

        $cf = new ConfigFile($this->config->baseSettings);
        $this->userPreferences->pageInit($cf);

        $formDisplay = new NaviForm($cf, 1);

        if (isset($_POST['revert'])) {
            // revert erroneous fields to their default values
            $formDisplay->fixErrors();
            Core::sendHeaderLocation('./index.php?route=/preferences/navigation');

            return;
        }

        $error = null;
        if ($formDisplay->process(false) && ! $formDisplay->hasErrors()) {
            // Load 2FA settings
            $twoFactor = new TwoFactor($cfg['Server']['user']);
            // save settings
            $result = $this->userPreferences->save($cf->getConfigArray());
            // save back the 2FA setting only
            $twoFactor->save();
            if ($result === true) {
                // reload config
                $this->config->loadUserPreferences();
                $tabHash = $_POST['tab_hash'] ?? null;
                $hash = ltrim($tabHash, '#');
                $this->userPreferences->redirect(
                    'index.php?route=/preferences/navigation',
                    null,
                    $hash
                );

                return;
            }

            $error = $result;
        }

        $this->addScriptFiles(['config.js']);

        $cfgRelation = $this->relation->getRelationsParam();

        $this->render('preferences/header', [
            'route' => $route,
            'is_saved' => ! empty($_GET['saved']),
            'has_config_storage' => $cfgRelation['userconfigwork'],
        ]);

        if ($formDisplay->hasErrors()) {
            $formErrors = $formDisplay->displayErrors();
        }

        $this->render('preferences/forms/main', [
            'error' => $error ? $error->getDisplay() : '',
            'has_errors' => $formDisplay->hasErrors(),
            'errors' => $formErrors ?? null,
            'form' => $formDisplay->getDisplay(
                true,
                Url::getFromRoute('/preferences/navigation'),
                ['server' => $server]
            ),
        ]);

        if ($this->response->isAjax()) {
            $this->response->addJSON('disableNaviSettings', true);

            return;
        }

        define('PMA_DISABLE_NAVI_SETTINGS', true);
    }
}
