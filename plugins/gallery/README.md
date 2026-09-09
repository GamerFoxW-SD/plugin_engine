# Gallery plugin

A galéria kizárólag a bejelentkezett felhasználó saját admin felületén jelenik meg.

## Funkciók

- csak `/admin` és `/admin/...` útvonalakon renderel;
- csak bejelentkezett felhasználónál működik;
- a felhasználó képei külön `storage/USER_ID/` könyvtárban vannak;
- JPG, PNG, GIF és WebP feltöltés;
- maximum 10 MB / kép;
- MIME-ellenőrzés fájltartalom alapján;
- 128 bites véletlen képazonosító;
- képek helyes, alkalmazás-alapútvonalas URL-en jelennek meg;
- a kép endpoint is csak bejelentkezett felhasználónak érhető el;
- a kép endpoint csak az aktuális felhasználó saját képét szolgálja ki.

## URL példa

Ha az alkalmazás itt fut:

`http://localhost/git/my-plugin-engine/`

akkor a képek URL-je:

`/git/my-plugin-engine/plugins/gallery/image.php?id=...`

Nem a domain gyökeréhez (`/plugins/...`) kötött abszolút útvonalat használ.

## Tárolás

```text
plugins/gallery/storage/
└── USER_ID/
    ├── metadata.json
    └── IMAGE_ID.ext
```

A storage könyvtárnak írhatónak kell lennie a PHP/Apache folyamat számára.

## Engine

Az Engine `src/` könyvtárának módosítása nem szükséges.
