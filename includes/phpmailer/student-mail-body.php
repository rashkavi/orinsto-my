<?php
function renderMailBody($verificationLink, $status)
{
    $currentYear = date('Y');
    $supportEmail = "infot@enviiz.com";
    $baseName = Project::getBaseName();
    $baseURL = Project::getBaseURL();
    $instituteLogo = Project::getInstituteEmailLogo();

    // Prepare content based on status
    if ($status == 1) {
        $subjectLine = "Complete Your {$baseName} System Registration";
        $message = <<<TEXT
 <p>Thank you for registering with the {$baseName} System. To complete your registration, please verify your email address and set your password by clicking the button below:</p>
                
TEXT;
        $buttonText = "Verify Email & Setup Password";
    } else {
        $subjectLine = "Password Reset Request";
        $message = <<<TEXT
<p>You recently requested to reset your Complete Your {$baseName} System Registration password. Please click the button below to proceed with creating a new password:</p>
<p><strong>Note:</strong>This password reset link will expire in 15 minutes. If you didn't request a password reset, please secure your account by contacting our support team immediately.</p>
TEXT;
        $buttonText = "Reset Your Password";
    }

    $image = <<<TEXT
    <div class="header">
        <img src="{$baseURL}/assets/imgs/{$instituteLogo}" alt="{$baseName} Logo" class="logo">
    </div>
    TEXT;

    // Professional email template
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$baseName - $subjectLine</title>
    <style type="text/css">
        /* Base Styles */
        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f7f9fc;
        }
        
        /* Typography */
        h1, h2, h3 {
            color: #002656;
            margin-top: 0;
        }
        
        h1 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        p {
            margin-bottom: 16px;
            font-size: 15px;
            color: #4a5568;
        }
        
        a {
            color: #3182ce;
            text-decoration: none;
        }
        
        /* Layout */
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            padding: 30px 0 20px;
        }
        
        .logo {
            max-width: 180px;
            height: auto;
        }
        
        .card {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 40px;
            margin-bottom: 20px;
        }
        
        /* Button Styles */
        .button-container {
            margin: 25px 0;
            text-align: center;
        }
        
        .button {
            display: inline-block;
            background-color: #002656;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 15px;
            line-height: 1.5;
            text-align: center;
            transition: all 0.2s ease;
        }
        
        .button:hover {
            background-color: #1a5a9bd3;
        }
        
        /* Divider */
        .divider {
            height: 1px;
            background-color: #e2e8f0;
            margin: 30px 0;
            border: 0;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 20px 0;
            color: #718096;
            font-size: 13px;
            line-height: 1.5;
        }
        
        .footer-links {
            margin: 10px 0;
        }
        
        .footer-links a {
            color: #718096;
            margin: 0 10px;
            text-decoration: none;
        }
        
        .footer-links a:hover {
            color: #3182ce;
        }
        
        /* Responsive */
        @media only screen and (max-width: 600px) {
            .container {
                padding: 10px;
            }
            
            .card {
                padding: 25px;
            }
            
            h1 {
                font-size: 20px;
            }
            
            p {
                font-size: 14px;
            }
            
            .button {
                padding: 10px 20px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header with Logo -->
       $image
        
        <!-- Main Content Card -->
        <div class="card">
            <h1>$subjectLine</h1>
            
            $message
            
            <!-- Action Button -->
            <center>
                <a href="$verificationLink" class="button">$buttonText</a>
            </center>
                                   
            <hr class="divider">
           
                     
            
            <p>Best regards,<br>The {$baseName} Team</p>
        </div>
        
        <!-- Footer -->
        <div style="text-align: center; padding: 20px 0; color: #718096; font-size: 13px; line-height: 1.5;">
                <div style="margin: 10px 0;">
                    <a href="https://enviiz.com/privacy-policy" style="color: #718096; margin: 0 10px; text-decoration: none;font-size: 12px;">Privacy Policy</a>
                    <a href="https://enviiz.com/terms-conditions" style="color: #718096; margin: 0 10px; text-decoration: none;font-size: 12px;">Terms of Service</a>
                    <a href="https://enviiz.com/contact-us" style="color: #718096; margin: 0 10px; text-decoration: none;font-size: 12px;">Contact Us</a>
                </div>
               
                <p style="margin: 10px 0; font-size: 12px; color: #718096; text-align: center;">
                    &copy;$currentYear ENLIXO Uni. All Rights Reserved |
                    Powered by
                    <a href="https://enviiz.com" target="_blank" style="text-decoration: none;">
                        <img src="https://dashboard.enviiz.com/assets/imgs/common/enviiz-theme.webp" alt="ENVIIZ Softwares (PVT) LTD" style="width: 35px; height: auto; vertical-align: middle;">
                    </a>
                </p>
        </div>
    </div>
</body>
</html>
HTML;

    return $html;
}
