# php-elearning-toolkit

Framework-freie Bausteine für E-Learning-Standards in PHP: SCORM 1.2/2004,
xAPI 1.0.3, cmi5 und LTI 1.3. Entstanden aus der Lernplattform von workDiary
(Feature 149); die SCORM-Teile waren dort bewusst ohne Laravel gebaut, damit
dieser Umzug ein Verschieben ist und kein Neuschreiben.

**Stand:** alle vier Bereiche umgesetzt und getestet, noch nicht veröffentlicht.
Benötigt `dschuppelius/php-common-toolkit` ab 1.34 (Zip-Eintragsfilter, UUID- und
IRI-Prüfung) und `web-token/jwt-library` 4.x für LTI.

## Bausteine

| Bereich | Wichtige Klassen | Zweck |
| --- | --- | --- |
| Pakete | `Package\PackageExtractor`, `Package\ExtractedPackage` | Lernpakete sicher entpacken (Zip-Slip, ausführbare Dateien, Größen), Paketbeschreibung im Unterordner finden |
| SCORM | `Scorm\Manifest`, `Scorm\CompletionRule`, `Scorm\Score`, `Scorm\LaunchPath` | Manifest namensraum-agnostisch lesen, Abschlussregel für 1.2 und 2004 |
| xAPI | `XApi\StatementValidator`, `XApi\Statement`, `XApi\ProgressRule` | Statements nach xAPI 1.0.3 prüfen und auf Fortschritt abbilden |
| cmi5 | `Cmi5\CourseStructure`, `Cmi5\LaunchUrl`, `Cmi5\LaunchData`, `Cmi5\Session`, `Cmi5\StatementRules`, `Cmi5\LmsStatements` | Kursstruktur, Start, Sitzungsregeln für AU-Statements, Statements des LMS |
| LTI 1.3 | `Lti\LoginInitiation`, `Lti\AuthenticationRequest`, `Lti\IdTokenBuilder`, `Lti\LaunchValidator`, `Lti\DeepLinkingResponse`, `Lti\Keys` | Login-Anstoß und Authentifizierungsanfrage beider Seiten (`LoginInitiation::toolLoginUrl()` für die Plattform), Launch als Plattform und als Tool, Deep Linking 2.0, Schlüssel und JWKS |

## Grundsätze

- **Sprachneutral.** Fehler tragen maschinelle Gründe (`$e->reason`), übersetzt wird
  in der Anwendung.
- **Kein versteckter Zustand.** Das Toolkit liest keine Uhr, erzeugt keine
  Zufallswerte und speichert nichts: Zeitpunkt, Statement-IDs, Sitzungs-IDs und der
  Nonce-Speicher (`Lti\NonceStore`) kommen vom Aufrufer.
- **Abgeschlossen ist nicht bestanden.** In SCORM, xAPI und cmi5 gleich: Ein
  `completed` mit Misserfolg erfüllt keine Einheit.
- **LTI nur mit RS256.** `none` und symmetrische Verfahren werden abgelehnt, bevor
  ein Schlüssel ins Spiel kommt; jeder Schlüssel trägt eine `kid`; zusätzliche,
  nicht vertrauenswürdige Empfänger in `aud` werden abgelehnt; die Nonce wird erst
  verbraucht, wenn alles andere stimmt.
- **Konstanten gegen die Spezifikation abgeglichen** (cmi5 Quartz, LTI 1.3 Core,
  Deep Linking 2.0, Security Framework 1.0), Stand 2026-09-14.

## Beispiele

```php
use ELearningToolkit\Package\{PackageException, PackageExtractor};
use ELearningToolkit\Scorm\Manifest;

try {
    $package = (new PackageExtractor(maxBytes: 512 * 1024 * 1024, maxFiles: 5000))->extract($zip, $target);
    $manifest = Manifest::fromXml($package->descriptorXml);
    $launch = $package->resolve((string) $manifest->launchHref);
} catch (PackageException $e) {
    // $e->reason: unreadable, too_many_files, too_large, path_escape, manifest_missing, …
}
```

```php
use ELearningToolkit\XApi\{Outcome, ProgressRule, Statement};

$statement = Statement::fromJson($body);          // wirft StatementException mit reason und path
if (ProgressRule::evaluate($statement) === Outcome::Completed) {
    // Einheit abschließen
}
```

```php
use ELearningToolkit\Lti\{InMemoryNonceStore, LaunchValidator, Registration};

$launch = (new LaunchValidator($nonceStore))->validate($idToken, $platformRegistration, new DateTimeImmutable(), $sessionNonce, $loginTargetLinkUri);
```

## Entwicklung

```bash
# gegen ein unveröffentlichtes common-toolkit im Nachbarordner
COMPOSER=composer.dev.json composer update
composer qa   # Pint, PHPStan (Level 8), PHPUnit
```

`composer.dev.json` und ihre Lock-Datei sind ignoriert und dürfen nicht committet werden.
