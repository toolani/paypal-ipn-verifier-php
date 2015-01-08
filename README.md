PayPal IPN Verifier
===================

A PayPal Instant Payment Notification (IPN) verifier class for PHP 5. 

Use the `IpnVerifier` class in your IPN script to handle the encoding 
of POST data, post back to PayPal, and parsing of the response from PayPal.

This class was based on (and is almost API-compatible with) [Quixotix/PHP-PayPal-IPN][1]. See below for differences and tips for porting your code.

[1]: https://github.com/Quixotix/PHP-PayPal-IPN

Features
--------

- Supports live and sandbox environments.
- Verifies an HTTP &quot;200&quot; response status code from the PayPal server.
- Get detailed plain text reports of the entire IPN using the `getTextReport()`
  method for use in emails and logs to administrators.
- Throws various exceptions to differentiate between common errors in code or
  server configuration versus invalid IPN responses.
  
Differences from Quixotix/PHP-PayPal-IPN
----------------------------------------

- Always verifies IPNs using SSL.
- Always uses cURL (no fsockopen support).
- PSR-2 compliant coding style.

Getting Started
---------------

This code is intended for web developers. You should understand how the IPN
process works conceptually and you should understand when and why you would be
using IPN. Reading the [PayPal Instant Payment Notification Guide][1] is a good
place to start.

You should also have a [PayPal Sandbox Account][2] with a test buyer account and a test seller account. When logged into your sandbox account there is an IPN simulator under the 'Test Tools' menu which you can used to test your IPN 
listener.

[2]: https://cms.paypal.com/cms_content/US/en_US/files/developer/IPNGuide.pdf
[3]: https://developer.paypal.com

Once you have your sandbox account set up, you simply create a PHP script that will be your IPN listener. In that script, use the `IpnVerifier()` class as shown below.

```php
<?php
use Toolani\Payment\Paypal\IpnVerifier;

// If you're doing this for real, autoloading would be better
include("/path/to/Toolani/Payment/Paypal/IpnVerifier.php");

$verifier = new IpnVerifier($useSandbox = true);

try {
    $verified = $verifier->verify($_POST);
} catch (Exception $e) {
    // fatal error trying to process IPN.
    exit(1);
}

if ($verified) {
    // IPN response was "VERIFIED"
} else {
    // IPN response was "INVALID"
}

// Verification status is available as a string, too. See table below
$status = $verifier->getVerificationStatusString();

```

Logging
-------

The `IpnVerifier` constructor optionally accepts a [PSR-3 compliant][4] logger object as its second parameter.

If provided, this will be used to log some basic info about each IPN and details of any errors during verification.

[4]: https://github.com/php-fig/log

Verification Status Strings
---------------------------

These are the possible status that may be returned by `getVerificationStatusString`. In normal operation, only 'VERIFIED' and 'INVALID' would be expected. Other statuses indicate errors.

Status|Description
------|-----------
VERIFIED    | IPN verified OK
INVALID     | PayPal reports that IPN is invalid
UNKNOWN     | We didn't get as far as trying to verify
ERROR       | Tried to verify and something went wrong
NO_DATA     | No data was provided to be verified
IPN_TIMEOUT | Verification request timed out

Quixotix/PHP-PayPal-IPN compatibility
-------------------------------------

It should be straightforward to port scripts using the PHP-PayPal-IPN class to use `IpnVerifier` instead, and a `processIpn()` method is provided to ease this.

The usage example above is directly equivalent to the [example][5] given in the PHP-PayPal-IPN documentation.

Things to be aware of:

- Sandbox configuration is now done via a constructor argument, rather than a public property, though the `useSandbox` property remains public (for the moment).
- There are no options for configuring cURL, SSL will always be used.
- The `verify` method requires that you explicitly provide the array of POSTed data. PHP-PayPal-IPN's `processIpn` did not and would automagically use `$_POST` if given no data. A `processIpn` method is thus provided, which mimics the old behaviour. **Note**: `verify` is the preferred method for verifying IPNs and `processIpn` should be considered deprecated and may be removed in future versions of this class.

[5]: https://github.com/Quixotix/PHP-PayPal-IPN/blob/master/README.md#getting-started


Example Report
--------------

Here is an example of a report returned by the `getTextReport()` method. Create your own reports by extending the `IpnVerifier` class or by accessing the data directly in your ipn script.

    --------------------------------------------------------------------------------
    [09/09/2011 8:35 AM] - https://www.sandbox.paypal.com/cgi-bin/webscr (curl)
    --------------------------------------------------------------------------------
    HTTP/1.1 200 OK
    Date: Fri, 09 Sep 2011 13:35:39 GMT
    Server: Apache
    X-Frame-Options: SAMEORIGIN
    Set-Cookie: c9MWDuvPtT9GIMyPc3jwol1VSlO=Ch-NORlHUjlmbEm__KG9LupR4mfMfQTkx1QQ6hHDyc0RImWr88NY_ILeICENiwtVX3iw4jEnT1-1gccYjQafWrQCkDmiykNT8TeDUg7R7L0D9bQm47PTG8MafmrpyrUAxQfst0%7c_jG1ZL6CffJgwrC-stQeqni04tKaYSIZqyqhFU7tKnV520wiYOw0hwk5Ehrh3hLDvBxkpm%7cYTFdl0w0YpEqxu0D1jDTVTlEGXlmLs4wob2Glu9htpZkFV9O2aCyfQ4CvA2kLJmlI6YiXm%7c1315575340; domain=.paypal.com; path=/; Secure; HttpOnly
    Set-Cookie: cookie_check=yes; expires=Mon, 06-Sep-2021 13:35:40 GMT; domain=.paypal.com; path=/; Secure; HttpOnly
    Set-Cookie: navcmd=_notify-validate; domain=.paypal.com; path=/; Secure; HttpOnly
    Set-Cookie: navlns=0.0; expires=Thu, 04-Sep-2031 13:35:40 GMT; domain=.paypal.com; path=/; Secure; HttpOnly
    Set-Cookie: Apache=10.72.109.11.1315575339707456; path=/; expires=Sun, 01-Sep-41 13:35:39 GMT
    X-Cnection: close
    Transfer-Encoding: chunked
    Content-Type: text/html; charset=UTF-8

    VERIFIED
    --------------------------------------------------------------------------------
    test_ipn                 1
    payment_type             instant
    payment_date             06:34:51 Sep 09, 2011 PDT
    payment_status           Completed
    address_status           confirmed
    payer_status             verified
    first_name               John
    last_name                Smith
    payer_email              buyer@paypalsandbox.com
    payer_id                 TESTBUYERID01
    address_name             John Smith
    address_country          United States
    address_country_code     US
    address_zip              95131
    address_state            CA
    address_city             San Jose
    address_street           123, any street
    business                 seller@paypalsandbox.com
    receiver_email           seller@paypalsandbox.com
    receiver_id              TESTSELLERID1
    residence_country        US
    item_name                something
    item_number              AK-1234
    quantity                 1
    shipping                 3.04
    tax                      2.02
    mc_currency              USD
    mc_fee                   0.44
    mc_gross                 12.34
    mc_gross_1               9.34
    txn_type                 web_accept
    txn_id                   51991334
    notify_version           2.1
    custom                   xyz123
    charset                  windows-1252
    verify_sign              Ah5rOpfPGo5g6FNg95DMPybP51J5AUEdXS1hqyRAP6WYYwaixKNDgQRR