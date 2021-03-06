# CalDAV Client Prototype

A CalDAV/WebDAV PHP client using Sabre's [vObject](https://github.com/fruux/sabre-vobject) & [Faker](https://github.com/fzaninotto/Faker) to generate fake iCal & vCard objects.

## Install

- 1/3: Add a new hostname entry in your `/etc/hosts/` file: You may choose `caldavclientprototype`
- 2/3: Install a new Apache2 vhost - Have a look at the [apache2.conf](doc/apache2.conf) example file.
- 3/3: Install web app:

```bash
git clone https://github.com/polypodes/CalDAVClientPrototype.git (or your own fork URL)
cd CalDAVClientPrototype
make
make install
```

When prompt ask you for your CalDAV login/password/host, fill in the required values using a (local/remote) calDav server
Do not forget to add this remote calDav to your `/etc/host` if it's a local dev-purpose server

Mind any `app_dev.php` IP issue.

Then go to [http://caldavclientprototype/app_dev.php](http://caldavclientprototype/app_dev.php)


## Functional testing

```bash
bin/phpunit -c app
```

## TODO

See [TODO.md](TODO.md)

## Fakely generates vObjects:

### vCard:

```
 BEGIN:VCARD
 VERSION:3.0
 PRODID:-//Sabre//Sabre VObject 3.3.5//EN
 FN:Jeannine-Olivie Huet
 TEL:0272666909
 EMAIL:leconte.capucine@millet.fr
 TEL;TYPE=fax:+33 (0)3 39 98 94 19
 END:VCARD
 ```

### vCal:
 
 ```
 BEGIN:VCALENDAR
 VERSION:2.0
 PRODID:-//Sabre//Sabre VObject 3.3.5//EN
 CALSCALE:GREGORIAN
 ORGANIZER:mailto:veronique93@brunel.net
 ATTENDEE:mailto:dupre.emmanuelle@sfr.fr
 ATTENDEE:mailto:matthieu.grenier@tele2.fr
 BEGIN:VEVENT
 SUMMARY:Voluptas impedit aut dignissimos non distinctio est sit.
 DTSTART;TZID=Europe/Zurich:20130608T161853
 RRULE:FREQ=YEARLY
 END:VEVENT
 ENDVCALENDAR
 ```
