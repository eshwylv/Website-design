<?php
declare(strict_types=1);
/* Coursework-safe mail configuration. PHP mail() is disabled by default because XAMPP normally has no SMTP relay configured.
   Set MAIL_ENABLED to true only after configuring a local/test SMTP environment. Never use real customer data for coursework testing. */
const MAIL_ENABLED = false;
const MAIL_FROM = 'no-reply@example.test';
const APP_BASE_URL = 'http://localhost/car_rental/public';
