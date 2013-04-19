<?php
/**
 * Shopware 4.0
 * Copyright © 2012 shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 *
 * @category   Shopware
 * @package    Shopware_Controllers_Widgets
 * @subpackage Widgets
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author     Heiner Lohaus
 * @author     $Author$
 */

/**
 * Shopware Captcha Controller
 *
 * todo@all: Documentation
 */
class Shopware_Controllers_Widgets_Captcha extends Enlight_Controller_Action
{
    /**
     * Pre dispatch action method
     *
     * Sets no render on some actions
     */
    public function preDispatch()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
    }

    /**
     * Index action method
     *
     * Creates a captcha and then outputs it.
     */
    public function indexAction()
    {
        $captcha = 'frontend/_resources/images/captcha/background.jpg';
        $font = 'frontend/_resources/images/captcha/font.ttf';

        $template_dirs = Shopware()->Template()->getTemplateDir();

        foreach ($template_dirs as $template_dir) {
            if (file_exists($template_dir . $captcha)) {
                $captcha = $template_dir . $captcha;
                break;
            }
        }
        foreach ($template_dirs as $template_dir) {
            if (file_exists($template_dir . $font)) {
                $font = $template_dir . $font;
                break;
            }
        }

        $random = $this->Request()->rand;

        $random = md5($random);
        $string = substr($random, 0, 5);

        if (file_exists($captcha)) {
            $im = imagecreatefromjpeg($captcha);
        } else {
            $im = imagecreatetruecolor(162, 87);
        }

        if (!empty(Shopware()->Config()->CaptchaColor)) {
            $colors = explode(',', Shopware()->Config()->CaptchaColor);
        } else {
            $colors = explode(',', '255,0,0');
        }

        $black = ImageColorAllocate($im, $colors[0], $colors[1], $colors[2]);

        $string = implode(' ', str_split($string));

        if (file_exists($font)) {
            for ($i = 0; $i <= strlen($string); $i++) {
                $rand1 = rand(35, 40);
                $rand2 = rand(15, 20);
                $rand3 = rand(60, 70);
                imagettftext($im, $rand1, $rand2, ($i + 1) * 15, $rand3, $black, $font, substr($string, $i, 1));
                imagettftext($im, $rand1, $rand2, (($i + 1) * 15) + 2, $rand3 + 2, $black, $font, substr($string, $i, 1));
            }
            for ($i = 0; $i < 8; $i++) {
                imageline($im, mt_rand(30, 70), mt_rand(0, 50), mt_rand(100, 150), mt_rand(20, 100), $black);
                imageline($im, mt_rand(30, 70), mt_rand(0, 50), mt_rand(100, 150), mt_rand(20, 100), $black);
            }
        } else {
            $white = ImageColorAllocate($im, 255, 255, 255);
            imagestring($im, 5, 40, 35, $string, $white);
            imagestring($im, 3, 40, 70, 'missing font', $white);
        }

        ob_start();
        imagejpeg($im, NULL, 90);
        imagedestroy($im);
        $i = ob_get_contents();

        $this->Response()->setHeader('Content-Type', 'image/jpeg', true);
        $this->Response()->setHeader('Content-Length', strlen($i));

        echo $i;
    }
}