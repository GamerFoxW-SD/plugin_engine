# PHP Plugin Engine

Framework-agnostic PHP 8.2+ plugin engine.

Az engine feladata kizárólag a pluginok életciklusának, függőségeinek, betöltésének és eseményalapú együttműködésének kezelése.

## Alapelv

* Egy plugin egyetlen funkcionális felelősség.
* A plugin nem komplett weboldal és nem komplett alkalmazás.
* Az engine nem ismeri a domain fogalmait: nincs benne `Portfolio`, `Project`, `Skill`, `Contact` stb.
* A pluginok csak explicit aktiválás után működnek.
* A pluginok eseményeken és az engine API-ján keresztül kommunikálnak.
* Egy hibás plugin `FAILED` állapotba kerülhet anélkül, hogy a többi plugin betöltését megállítaná.
* Deaktiváláskor a plugin saját event listenerei automatikusan eltávolításra kerülnek.
* Az engine működéséhez nincs szükség Composerre vagy külső PHP csomagra.
* A projekt közvetlenül futtatható XAMPP vagy cPanel alatt, külön parancssori indítás nélkül.

## Plugin lifecycle

```text
DISCOVERED -> LOADED -> ACTIVE
```

Hiba esetén:

```text
DISCOVERED/LOADED -> FAILED
```

Deaktiválás:

```text
ACTIVE -> INACTIVE
```

## Projektstruktúra

```text
my-plugin-engine/

├── index.php
├── autoload.php
│
├── config/
│   └── plugins.php
│
├── plugins/
│   ├── hello/
│   │   ├── plugin.json
│   │   ├── src/
│   │   │   └── HelloPlugin.php
│   │   └── assets/
│   │       ├── hello.css
│   │       └── hello.js
│   │
│   └── theme/
│       ├── plugin.json
│       ├── src/
│       │   └── ThemePlugin.php
│       └── assets/
│           └── theme.css
│
├── src/
│   ├── Contracts/
│   │   └── PluginInterface.php
│   │
│   ├── Core/
│   │   ├── Engine.php
│   │   ├── PluginContext.php
│   │   ├── PluginDescriptor.php
│   │   ├── PluginLoader.php
│   │   ├── PluginManager.php
│   │   ├── PluginRecord.php
│   │   ├── PluginRegistry.php
│   │   └── PluginState.php
│   │
│   ├── Events/
│   │   └── EventDispatcher.php
│   │
│   ├── Exceptions/
│   │   ├── PluginException.php
│   │   └── PluginDependencyException.php
│   │
│   └── Rendering/
│       └── RenderEvent.php
│
└── tests/
    └── smoke.php
```

## Autoloading

Az engine saját PSR-4 jellegű autoloadert használ.

A projekt belépési pontja:

```php
require __DIR__ . '/autoload.php';
```

Nincs szükség:

```text
vendor/
composer.json
composer install
```

A PHP osztályok az `Engine\` namespace alapján töltődnek be a `src/` könyvtárból.

Például:

```text
Engine\Core\Engine
```

automatikusan:

```text
src/Core/Engine.php
```

fájlból kerül betöltésre.

A pluginok saját osztályfájljaikat a plugin loader tölti be a plugin manifest alapján.

## Plugin manifest

Minden plugin saját `plugin.json` fájllal rendelkezik.

Példa:

```json
{
    "name": "hello",
    "version": "1.1.0",
    "entry": "HelloPlugin",
    "namespace": "Hello",
    "dependencies": []
}
```

A manifest fő mezői:

| Mező           | Jelentés                        |
| -------------- | ------------------------------- |
| `name`         | A plugin egyedi neve            |
| `version`      | A plugin verziója               |
| `entry`        | A belépési osztály neve         |
| `namespace`    | A plugin namespace-e            |
| `dependencies` | Más pluginoktól való függőségek |

Például:

```text
plugins/hello/src/HelloPlugin.php
```

```php
namespace Hello;

final class HelloPlugin implements PluginInterface
{
    // ...
}
```

A teljes osztálynév:

```text
Hello\HelloPlugin
```

## Plugin interface

Minden plugin az alábbi interfészt implementálja:

```php
interface PluginInterface
{
    public function getName(): string;

    public function getVersion(): string;

    public function register(PluginContext $context): void;

    public function boot(PluginContext $context): void;

    public function shutdown(PluginContext $context): void;
}
```

### `register()`

A plugin infrastruktúráját, event listenereit és egyéb regisztrációit végzi.

### `boot()`

A plugin tényleges aktiválásakor futó inicializáció.

### `shutdown()`

A plugin deaktiválásakor futó leállítási logika.

## Saját plugin készítése

Egy minimális plugin:

```php
<?php

declare(strict_types=1);

namespace Example;

use Engine\Contracts\PluginInterface;
use Engine\Core\PluginContext;

final class ExamplePlugin implements PluginInterface
{
    public function getName(): string
    {
        return 'example';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function register(PluginContext $context): void
    {
        // Event listenerek és egyéb regisztrációk.
    }

    public function boot(PluginContext $context): void
    {
        // Aktiváláskor fut.
    }

    public function shutdown(PluginContext $context): void
    {
        // Deaktiváláskor fut.
    }
}
```

A plugin könyvtárának legalább a következőket kell tartalmaznia:

```text
plugins/example/
├── plugin.json
└── src/
    └── ExamplePlugin.php
```

## Event rendszer

A pluginok eseményeken keresztül tudnak együttműködni.

Példa egy render event listenerre:

```php
public function register(PluginContext $context): void
{
    $context->listen(
        RenderEvent::class,
        function (RenderEvent $event): void {
            $event->addSection(
                '<p>Saját plugin tartalom</p>'
            );
        }
    );
}
```

A `PluginContext::listen()` automatikusan az aktuális pluginhoz köti a listenert.

Ez lehetővé teszi, hogy deaktiváláskor az engine automatikusan eltávolítsa az adott pluginhoz tartozó listenereket.

Így egy plugin deaktiválása nem hagy maga után aktív event listenereket.

## Rendering

A `RenderEvent` egy általános, domain-agnosztikus renderelési esemény.

A pluginok például hozzáadhatnak:

* HTML sectiont
* CSS fájlt
* JavaScript fájlt
* oldal címet

Példa:

```php
$event->addStyle(
    '/plugins/example/assets/example.css'
);

$event->addScript(
    '/plugins/example/assets/example.js'
);

$event->addSection(
    '<section class="example">
        Hello from plugin
    </section>'
);
```

Az engine nem tudja, hogy ezek pontosan milyen domainhez tartoznak.

A renderelésért felelős alkalmazási réteg dönti el, hogyan jeleníti meg az eseményben összegyűjtött tartalmat.

## Explicit aktiválás

A pluginok felfedezése és betöltése **nem jelenti automatikusan az aktiválásukat**.

A futtató alkalmazás dönti el, mely pluginok legyenek aktívak:

```php
$engine->boot();

$engine->plugins()->activate('theme');
$engine->plugins()->activate('hello');
```

A projektben az aktív pluginok a következő konfigurációban vannak megadva:

```text
config/plugins.php
```

Például:

```php
return [
    'active' => [
        'theme',
        'hello',
    ],
];
```

Az engine ettől továbbra is domain-agnosztikus marad.

## Plugin függőségek

A pluginok deklarálhatnak függőségeket:

```json
{
    "name": "example",
    "version": "1.0.0",
    "entry": "ExamplePlugin",
    "namespace": "Example",
    "dependencies": {
        "theme": "^1.0"
    }
}
```

Az engine:

* ellenőrzi, hogy a függőség létezik-e,
* betölti a szükséges plugint,
* aktiválja a függőségeket,
* felismeri a körkörös függőségeket.

Példa:

```text
Plugin A
   ↓
Plugin B
   ↓
Plugin C
```

Ha viszont:

```text
Plugin A
   ↓
Plugin B
   ↓
Plugin A
```

akkor az engine `PluginDependencyException` hibát jelez.

## Hibakezelés

Egy hibás plugin nem állítja le automatikusan a teljes engine-t.

Például:

```text
theme  -> ACTIVE
hello  -> ACTIVE
broken -> FAILED
```

A `broken` plugin hibája nem akadályozza meg a `theme` és `hello` pluginok működését.

A plugin állapotához tartozó hiba a `PluginRecord` objektumban megőrzésre kerül.

Ez lehetővé teszi, hogy egy hibás vagy elavult plugin külön kezelhető legyen anélkül, hogy szükségszerűen az egész engine-t módosítani kellene.

## XAMPP használata

A projekt közvetlenül futtatható XAMPP alatt.

A projektet másold a XAMPP `htdocs` könyvtárába:

```text
C:\xampp\htdocs\my-plugin-engine
```

Indítsd el az **Apache** modult a XAMPP Control Panelben.

Ezután böngészőben:

```text
http://localhost/my-plugin-engine/
```

Nincs szükség:

```text
php -S
composer install
parancssori indítás
```

A belépési pont:

```text
index.php
```

közvetlenül a projekt gyökerében található.

## cPanel használata

A projekt cPaneles tárhelyen is futtatható, amennyiben a tárhelyen PHP 8.2 vagy újabb verzió érhető el.

Például:

```text
public_html/
└── my-plugin-engine/
    ├── index.php
    ├── autoload.php
    ├── config/
    ├── plugins/
    └── src/
```

Ezután a projekt közvetlenül böngészőből érhető el:

```text
https://sajat-domain.hu/my-plugin-engine/
```

Nem szükséges SSH vagy parancssori PHP szerver.

## Ellenőrzés

A PHP fájlok külső csomag nélkül is szintaktikailag ellenőrizhetők.

PowerShell alatt például:

```powershell
Get-ChildItem src, plugins, tests -Recurse -Filter *.php |
    ForEach-Object {
        C:\xampp\php\php.exe -l $_.FullName
    }
```

Vagy egyesével:

```powershell
C:\xampp\php\php.exe -l src\Core\Engine.php
```

A projekt működéséhez nincs szükség Composerre.

## Tervezési cél

Az engine célja egy általános, újrafelhasználható plugin infrastruktúra biztosítása.

Az engine **nem tartalmazhat domain-specifikus logikát**.

Helyes:

```text
Engine
├── Plugin loading
├── Plugin lifecycle
├── Dependencies
├── Events
└── Rendering API
```

Helytelen:

```text
Engine
├── Portfolio
├── Projects
├── Skills
├── Contact
└── About
```

Ezeknek külön pluginokban kell megjelenniük.

A cél egy olyan rendszer, ahol egy funkció cseréje vagy hibája lehetőleg csak az adott plugin módosítását igényli, miközben az engine változatlan marad.

A rendszer ezáltal alkalmas arra is, hogy egy teljes weboldal vagy alkalmazás funkcionalitása több, egymástól lazán függő pluginból épüljön fel.

Az engine nem maga az alkalmazás.

**Az engine az infrastruktúra, amely lehetővé teszi az alkalmazás pluginokból történő felépítését.**
