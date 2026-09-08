<?php
declare(strict_types=1);
namespace PortfolioAdmin\Controllers;
use PortfolioDatabase\PortfolioDatabasePlugin;
use PortfolioTheme\PortfolioThemePlugin;

final class AdminController {
    private $base = '/git/my-plugin-engine';
    public function __construct(private PortfolioDatabasePlugin $db, private PortfolioThemePlugin $theme) {}
    private function esc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
    private function userId(): int { return (int)($_SESSION['portfolio_user_id'] ?? 0); }
    private function requireLogin(): ?string {
        if ($this->userId() <= 0) return '<section class="admin"><h1>Bejelentkezés szükséges</h1><p><a href="./admin?login=1">Bejelentkezés</a></p></section>';
        return null;
    }
    public function handle(string $path, array $post, array $get): string {
        if (isset($get['logout'])) { unset($_SESSION['portfolio_user_id']); header('Location: ./admin'); exit; }
        if (isset($get['login'])) return $this->login($post);
        if ($x = $this->requireLogin()) return $x;
        $uid = $this->userId();
        if (str_contains($path, '/admin/profile')) return $this->profile($uid, $post);
        if (str_contains($path, '/admin/theme')) return $this->theme($uid, $post);
        if (str_contains($path, '/admin/skills')) return $this->skills($uid, $post, $get);
        if (str_contains($path, '/admin/projects')) return $this->projects($uid, $post, $get);
        return $this->dashboard($uid);
    }
    private function login(array $post): string {
        $error='';
        if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
            $u=$this->db->users()->findByEmail(trim((string)($post['email']??'')));
            if ($u && password_verify((string)($post['password']??''), (string)$u['password_hash'])) {
                session_regenerate_id(true); $_SESSION['portfolio_user_id']=(int)$u['id']; header('Location: ./admin'); exit;
            }
            $error='Hibás email vagy jelszó.';
        }
        return '<section class="admin-login"><h1>Bejelentkezés</h1>'.($error?'<p class="alert">'.$this->esc($error).'</p>':'').'<form method="post"><input type="email" name="email" required placeholder="Email"><input type="password" name="password" required placeholder="Jelszó"><button>Belépés</button></form><p><a href="./register">Regisztráció</a></p></section>';
    }
    private function dashboard(int $uid): string {
        $u=$this->db->users()->find($uid); $projects=$this->db->projects()->forUser($uid); $skills=$this->db->userSkills()->forUser($uid);
        return '<section class="admin"><nav>'.$this->nav().'</nav><h1>Saját Portfolio admin</h1><p>Bejelentkezve: <b>'.$this->esc($u['name']??'').'</b></p><div class="stats"><b>Skillek '.count($skills).'</b><b>Projektek '.count($projects).'</b></div><p><a class="portfolio-button" href="./u/'.$uid.'">Saját portfolio megtekintése</a></p></section>';
    }
    private function nav(): string
{
    

    return '
        <a href="' . $this->base . '/admin">Dashboard</a>
        <a href="' . $this->base . '/admin/profile">Profil</a>
        <a href="' . $this->base . '/admin/skills">Skillek</a>
        <a href="' . $this->base . '/admin/projects">Projektek</a>
        <a href="' . $this->base . '/admin/theme">Megjelenés</a>
        <a href="' . $this->base . '/admin?logout=1">Kilépés</a>
    ';
}
    private function profile(int $uid, array $post): string {
        $u=$this->db->users()->find($uid);
        if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
            $this->db->users()->update($uid,[
                'name'=>trim((string)($post['name']??'')), 'email'=>trim((string)($post['email']??'')),
                'phone'=>trim((string)($post['phone']??''))?:null, 'profile_image'=>trim((string)($post['profile_image']??''))?:null,
                'contact_visible'=>isset($post['contact_visible'])?1:0, 'is_admin'=>(int)($u['is_admin']??0)
            ]);
            $u=$this->db->users()->find($uid);
        }
        return '<section class="admin"><nav>'.$this->nav().'</nav><h1>Saját profil</h1><form method="post"><label>Név<input name="name" required value="'.$this->esc($u['name']).'"></label><label>Email<input type="email" name="email" required value="'.$this->esc($u['email']).'"></label><label>Telefon<input name="phone" value="'.$this->esc($u['phone']).'"></label><label>Profilkép URL<input name="profile_image" value="'.$this->esc($u['profile_image']).'"></label><label><input type="checkbox" name="contact_visible" '.($u['contact_visible']?'checked':'').'> Elérhetőség látható</label><button>Mentés</button></form></section>';
    }

private function skills(int $uid, array $post, array $get): string
{
    // Skill törlése
    if (isset($get['remove'])) {
        $this->db->userSkills()->remove($uid, (int)$get['remove']);
    }

    // Új skill hozzáadása
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $name = trim((string)($post['name'] ?? ''));

        if ($name !== '') {
            // Ha létezik, visszaadja az ID-ját,
            // ha nem létezik, létrehozza.
            $sid = $this->db->skills()->findOrCreate($name);

            // Hozzáadás a felhasználóhoz
            $this->db->userSkills()->add($uid, $sid);
        }
    }

    // Felhasználó jelenlegi skilljei
    $items = '';

    foreach ($this->db->userSkills()->forUser($uid) as $s) {
        $items .= '<li>'
            . $this->esc($s['name'])
            . ' <a href="./admin/skills?remove=' . (int)$s['id'] . '">×</a>'
            . '</li>';
    }

    // ÖSSZES skill az adatbázisból
    $allSkills = $this->db->skills()->all();

    $skillOptions = '';

    foreach ($allSkills as $skill) {
        $skillOptions .= '<div class="skill-option" data-value="'
            . $this->esc($skill['name'])
            . '">'
            . $this->esc($skill['name'])
            . '</div>';
    }

    return '
    <section class="admin">
        <nav>'.$this->nav().'</nav>

        <h1>Saját skillek</h1>

        <form method="post" id="skill-form">

            <div class="skill-autocomplete">

                <input
                    type="text"
                    name="name"
                    id="skill-input"
                    required
                    autocomplete="off"
                    placeholder="PHP"
                >

                <div id="skill-list" class="skill-list">
                    '.$skillOptions.'
                </div>

            </div>

            <button type="submit">Hozzáadás</button>

        </form>

        <ul>
            '.$items.'
        </ul>

    </section>

    <script>
    document.addEventListener("DOMContentLoaded", function () {

        const input = document.getElementById("skill-input");
        const list = document.getElementById("skill-list");

        if (!input || !list) return;

        const options = Array.from(
            list.querySelectorAll(".skill-option")
        );

        input.addEventListener("input", function () {

            const value = this.value.trim().toLowerCase();

            let visible = 0;

            options.forEach(function (option) {

                const name = option.dataset.value.toLowerCase();

                if (value === "" || name.includes(value)) {
                    option.style.display = "block";
                    visible++;
                } else {
                    option.style.display = "none";
                }

            });

            list.style.display = visible > 0 ? "block" : "none";
        });

        options.forEach(function (option) {

            option.addEventListener("click", function () {

                input.value = this.dataset.value;

                list.style.display = "none";

                input.focus();
            });

        });

        input.addEventListener("focus", function () {

            if (this.value.trim() !== "") {
                this.dispatchEvent(new Event("input"));
            }

        });

        document.addEventListener("click", function (event) {

            if (!event.target.closest(".skill-autocomplete")) {
                list.style.display = "none";
            }

        });

    });
    </script>

    <style>
    .skill-autocomplete {
        position: relative;
        display: inline-block;
    }

    .skill-list {
        display: none;
        position: absolute;
        left: 0;
        right: 0;
        top: 100%;
        z-index: 1000;
        background: #3f3f3f;
        border: 1px solid #ccc;
        max-height: 200px;
        overflow-y: auto;
    }

    .skill-option {
        padding: 8px 10px;
        cursor: pointer;
    }

    .skill-option:hover {
        background: #ae85ff;
    }
    </style>
    ';
}

    private function projects(int $uid, array $post, array $get): string {
        if (isset($get['delete'])) { $p=$this->db->projects()->find((int)$get['delete']); if($p && (int)$p['user_id']===$uid) $this->db->projects()->delete((int)$p['id']); }
        if (($_SERVER['REQUEST_METHOD']??'GET')==='POST' && isset($post['title'])) $this->db->projects()->create($uid,(string)$post['title'],(string)($post['description']??''));
        if (($_SERVER['REQUEST_METHOD']??'GET')==='POST' && isset($post['link_url'], $post['project_id'])) { $p=$this->db->projects()->find((int)$post['project_id']); if($p && (int)$p['user_id']===$uid) $this->db->links()->create((int)$p['id'],(string)($post['link_title']??'Link'),(string)$post['link_url'],(string)($post['link_type']??'website')); }
        if (isset($get['delete_link'])) { $l=$this->db->links()->find((int)$get['delete_link']); if($l && (int)$l['user_id']===$uid) $this->db->links()->delete((int)$l['id']); }
        $projects=$this->db->projects()->forUser($uid); $opts=''; $rows='';
        foreach($projects as $p){$opts.='<option value="'.$p['id'].'">'.$this->esc($p['title']).'</option>'; $rows.='<li><b>'.$this->esc($p['title']).'</b> <a href="'.$this->base.'/project/'.$p['id'].'">megtekintés</a> <a href="'.$this->base.'/admin/projects/project-skills?project_id='.$p['id'].'">skillek</a> <a href="'.$this->base.'/admin/projects?delete='.$p['id'].'">×</a></li>';}
        return '<section class="admin"><nav>'.$this->nav().'</nav><h1>Saját projektek</h1><form method="post"><input name="title" required placeholder="Projekt címe"><textarea name="description" required placeholder="Leírás"></textarea><button>Projekt létrehozása</button></form><h2>Link hozzáadása</h2><form method="post"><select name="project_id" required>'.$opts.'</select><input name="link_title" required placeholder="Link címe"><input name="link_url" type="url" required placeholder="https://..."><select name="link_type"><option value="git">Git</option><option value="website">Weboldal</option><option value="documentation">Dokumentáció</option><option value="video">Videó</option><option value="img">Kép</option><option value="other">Egyéb</option></select><button>Link hozzáadása</button></form><ul>'.$rows.'</ul></section>';
    }
    private function theme(int $uid, array $post): string {
        if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') $this->theme->saveForUser($uid,$post);
        $s=$this->theme->settingsForUser($uid);
        return '<section class="admin"><nav>'.$this->nav().'</nav><h1>Saját portfolio megjelenése</h1><p>Ezek a beállítások csak a saját <code>/u/'.$uid.'</code> oldaladon és a saját projektoldalaidon érvényesek.</p><form method="post"><label>Primary<input type="color" name="primary" value="'.$this->esc($s['primary']).'"></label><label>Secondary<input type="color" name="secondary" value="'.$this->esc($s['secondary']).'"></label><label>Background<input type="color" name="background" value="'.$this->esc($s['background']).'"></label><label>Text<input type="color" name="text" value="'.$this->esc($s['text']).'"></label><label>Card<input type="color" name="card" value="'.$this->esc($s['card']).'"></label><label>Radius<input type="text" name="radius" value="'.$this->esc($s['radius']).'"></label><label>Custom CSS<textarea name="custom_css">'.$this->esc($s['custom_css']).'</textarea></label><button>Mentés</button></form></section>';
    }
}
