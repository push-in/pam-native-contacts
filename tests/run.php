<?php

declare(strict_types=1);

use Pam\Native\Contacts\Contact;
use Pam\Native\Contacts\Contacts;
use Pam\Native\Contacts\Sms;
use Pam\Native\Internal\Runtime;
use Pam\Native\Internal\Wire;
use Pam\Native\ModuleResultStatus;

require __DIR__.'/harness.php';

$contacts = null;
$contactsRequestId = Contacts::all(static function (array $items) use (&$contacts): void {
    $contacts = $items;
});
$contactsCall = TestDiagnostics::$moduleCall;
$assert(
    $contactsCall !== null
        && $contactsCall['requestId'] === $contactsRequestId
        && $contactsCall['module'] === 'contacts'
        && $contactsCall['method'] === 'list'
        && Wire::decodeMap($contactsCall['payload']) == ['offset' => 0, 'limit' => 250],
    'Contacts facade did not request the first bounded native page.',
);
Runtime::dispatchModuleResult(
    $contactsRequestId,
    ModuleResultStatus::Success->value,
    Wire::map([
        'items' => json_encode([[
            'id' => '42',
            'displayName' => 'Ada Lovelace',
            'givenName' => 'Ada',
            'familyName' => 'Lovelace',
            'phoneNumbers' => ['+5511999999999'],
            'emailAddresses' => ['ada@example.test'],
        ]], JSON_THROW_ON_ERROR),
        'hasMore' => false,
    ]),
);

$assert(
    is_array($contacts)
        && count($contacts) === 1
        && $contacts[0] instanceof Contact
        && $contacts[0]->displayName === 'Ada Lovelace'
        && $contacts[0]->phoneNumbers === ['+5511999999999'],
    'Contacts facade did not decode native contacts into typed values.',
);

$smsAvailable = null;
$smsAvailabilityRequest = Sms::isAvailable(
    static function (bool $available) use (&$smsAvailable): void {
        $smsAvailable = $available;
    },
);
$smsAvailabilityCall = TestDiagnostics::$moduleCall;
$assert(
    $smsAvailabilityCall !== null
        && $smsAvailabilityCall['requestId'] === $smsAvailabilityRequest
        && $smsAvailabilityCall['module'] === 'sms'
        && $smsAvailabilityCall['method'] === 'isAvailable',
    'SMS facade must query platform availability.',
);
Runtime::dispatchModuleResult(
    $smsAvailabilityRequest,
    ModuleResultStatus::Success->value,
    Wire::map(['available' => true]),
);
$assert($smsAvailable === true, 'SMS facade must decode platform availability.');

$smsOpened = false;
$smsComposeRequest = Sms::compose(
    [' +55 11 99999-0000 ', '+55 11 99999-0000'],
    'Convite do Zé Chat',
    static function () use (&$smsOpened): void {
        $smsOpened = true;
    },
);
$smsComposeCall = TestDiagnostics::$moduleCall;
$assert(
    $smsComposeCall !== null
        && $smsComposeCall['requestId'] === $smsComposeRequest
        && $smsComposeCall['module'] === 'sms'
        && $smsComposeCall['method'] === 'compose'
        && Wire::decodeMap($smsComposeCall['payload']) == [
            'recipients' => '+55 11 99999-0000',
            'body' => 'Convite do Zé Chat',
        ],
    'SMS facade must normalize recipients and encode the draft.',
);
Runtime::dispatchModuleResult(
    $smsComposeRequest,
    ModuleResultStatus::Success->value,
    '',
);
$assert($smsOpened, 'SMS facade must report a presented composer.');
$smsFailure = '';
$smsFailureRequest = Sms::compose(
    ['+5511999990000'],
    'Convite',
    failed: static function (string $message) use (&$smsFailure): void {
        $smsFailure = $message;
    },
);
Runtime::dispatchModuleResult(
    $smsFailureRequest,
    ModuleResultStatus::Failure->value,
    'SMS unavailable',
);
$assert(
    $smsFailure === 'SMS unavailable',
    'SMS facade must expose recoverable native failures to applications.',
);

$created = null;
$contactFailure = null;
Contacts::openNewContactForm(static function (bool $value) use (&$created): void { $created = $value; });
$assert($lastCall()['module'] === 'contacts' && $lastCall()['method'] === 'openNewContactForm', 'Contacts::openNewContactForm must call the contacts module.');
Runtime::dispatchModuleResult($lastCall()['requestId'], ModuleResultStatus::Success->value, Wire::map(['created' => true]));
$assert($created === true, 'A saved contact must answer created = true.');
Contacts::openNewContactForm(
    static function (bool $value) use (&$created): void { $created = $value; },
    static function (string $error) use (&$contactFailure): void { $contactFailure = $error; },
);
Runtime::dispatchModuleResult($lastCall()['requestId'], ModuleResultStatus::Success->value, Wire::map(['created' => false]));
$assert($created === false && $contactFailure === null, 'A cancelled form must answer created = false.');
Contacts::openNewContactForm(
    static fn (bool $_) => null,
    static function (string $error) use (&$contactFailure): void { $contactFailure = $error; },
);
Runtime::dispatchModuleResult($lastCall()['requestId'], ModuleResultStatus::Failure->value, 'No contacts app can create a contact');
$assert($contactFailure === 'No contacts app can create a contact', 'Contact form failures must reach $failure without throwing.');



echo "pam-native-contacts: {$tests} assertions passed\n";
