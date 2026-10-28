# PAM Native Contacts

Device contacts and the SMS composer for PAM Native apps: paged contact reads, the system new-contact form and a prefilled SMS composer.

Moved out of the PAM Native core in 1.46: the core keeps only the base every
app needs, and this feature ships as a plugin. The native module name and its
wire payloads did not change.

## Install

```bash
composer require pushinbr/pam-native-contacts
```

Requires `pushinbr/pam-native` 1.46 or newer (plugin hooks `WatchChannel` and
`ActivityResultHost`). The plugin is discovered from Composer; no manual
registration is needed.

## Use

```php
use Pam\Native\Contacts\Contact;
use Pam\Native\Contacts\Contacts;
use Pam\Native\Contacts\Sms;

Contacts::all(static function (array $contacts): void {
    // list<Contact>: displayName, phoneNumbers, emailAddresses
});
Sms::compose(['+5511999999999'], 'Hi!', static function (): void {});
```

## Native

- Module: `contacts`, `sms` (Android and iOS).
- Permissions: `android.permission.READ_CONTACTS`; iOS `NSContactsUsageDescription`.
- Android dependencies: none

## Migrating from the core

The 1.46 core keeps the old class names (`Pam\Native\System\...` and the
top-level value types) as deprecated aliases of this plugin's classes; they
are removed in 1.47. Replace the imports with the `Pam\Native\...` names
above.

## Tests

```bash
php tests/run.php            # after composer install
PAM_NATIVE_SRC=../pam-native/packages/native/src php tests/run.php
```
