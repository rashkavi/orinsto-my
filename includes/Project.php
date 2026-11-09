<?php
class Project
{
    private const BASE_NAME = "Orinsto Campus";
    private const BASE_URL = "https://dashboard.orinstolms.com";
    private const BASE_PORTAL_URL = "https://portal.orinstolms.com";
    private const INSTITUTE_LOGO = "common/logo.png";
    private const INSTITUTE_EMAIL_LOGO = "common/logo.png";
    private const VERSION = "2.4.33";

    private const WHATSAPPNUMBER = "";
    private const WHATSAPP_API = "";

    public static function getBaseName()
    {
        return self::BASE_NAME;
    }
    public static function getBaseUrl()
    {
        return self::BASE_URL;
    }
    public static function getBasePortalUrl()
    {
        return self::BASE_PORTAL_URL;
    }
    public static function getInstituteLogo()
    {
        return self::INSTITUTE_LOGO;
    }

    public static function getVersion()
    {
        return self::VERSION;
    }

    public static function getInstituteEmailLogo()
    {
        return self::INSTITUTE_EMAIL_LOGO;
    }
    public static function getWhatsAppNumber()
    {
        return self::WHATSAPPNUMBER;
    }
    public static function getWhatsAppApi()
    {
        return self::WHATSAPP_API;
    }
    public static function getGoogleVisionKeyPath()
    {
        if (in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1'])) {
            $path = __DIR__ . '/../secure/google-vision-key.json';
        } else {
            $path = dirname($_SERVER['DOCUMENT_ROOT'], 3) . '/orinstolms.com/secure/google-vision-key.json';
        }

        if (!file_exists($path)) {
            throw new Exception("Google Vision API key file not found at: " . $path);
        }

        return $path;
    }
}
