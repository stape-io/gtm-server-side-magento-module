<?php

namespace Stape\Gtm\Model\Data\Webhook;

class CookieList
{
    /**
     * Retrieve entire list of cookies
     *
     * @return string[]
     */
    public function getAll()
    {
        return [
            '_fbc',
            '_fbp',
            'FPGCLAW',
            '_gcl_aw',
            'ttclid',
            '_dcid',
            'FPID',
            'FPLC',
            '_ttp',
            'FPGCLGB',
            'li_fat_id',
            'taboola_cid',
            'outbrain_cid',
            'impact_cid',
            '_epik',
            '_scid',
            '_scclid',
            '_uetmsclkid',
            '_ga',
            'FPAU',
            '_gcl_au',
            '_gcl_gb',
            '_gcl_gs',
            '_gcl_dc',
            'FPGCLGS',
            'FPGCLDC',
            'FPGSID',
            'awin_awc',
            'awin_source',
            'rakuten_site_id',
            'rakuten_time_entered',
            'rakuten_ran_mid',
            'rakuten_ran_eaid',
            'rakuten_ran_site_id',
            'cje',
            'stape_klaviyo_kx',
            'stape_klaviyo_email',
            'Stape',
            'euconsent-v2',
            'addtl_consent',
            'usprivacy',
            'OptanonConsent',
            'CookieConsent',
            'didomi_token',
            'didomi_dcs',
            'axeptio_cookies',
            'axeptio_authorized_vendors',
            'cookieyes-consent',
            'complianz_consent_status',
            'borlabs-cookie',
            'uc_settings'
        ];
    }

    /**
     * @return string[]
     */
    public function getWildCardCookies()
    {
        return [
            '_iub_cs-',
            'cmplz_'
        ];
    }

    /**
     * Check if cookie is allowed
     *
     * @param string $cookieName
     * @return bool
     */
    public function isAllowedCookie($cookieName)
    {
        if (in_array($cookieName, $this->getAll())) {
            return true;
        }

        foreach ($this->getWildCardCookies() as $wildcard) {
            if (stripos($cookieName, $wildcard) !== false) {
                return true;
            }
        }

        return false;
    }
}
