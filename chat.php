<?php
session_start();

$role = $_SESSION['role'] ?? '';

if ($role === 'etudiant' && !empty($_SESSION['id_etu'])) {
    $nom = 'Étudiant';
    $retour = 'espace_etu.php';
} elseif ($role === 'surveillant' && !empty($_SESSION['id_surv'])) {
    $nom = 'Surveillant';
    $retour = 'espace_surv.php';
} else {
    header('Location: login_etu.php');
    exit;
}

if (!isset($_SESSION['chat_csrf'])) {
    $_SESSION['chat_csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Messagerie</title>

<style>
*{box-sizing:border-box}
body{font-family:Arial,sans-serif;background:#f4f6f9;margin:0;padding:20px}
.app{max-width:950px;margin:20px auto;background:white;border-radius:10px;overflow:hidden;box-shadow:0 3px 12px #0001}
header{background:#003366;color:white;padding:18px}
.layout{display:grid;grid-template-columns:260px minmax(0,1fr)}
.layout.sans-contacts{grid-template-columns:minmax(0,1fr)}
.contacts{padding:15px;border-right:1px solid #ddd}
.contact{display:block;width:100%;text-align:left;margin:5px 0;background:#e8edf3;color:#123;padding:12px;border:0;border-radius:5px;cursor:pointer;overflow-wrap:anywhere}
.contact.active{background:#0b548f;color:white}
#messages{height:420px;overflow-y:auto;padding:18px;background:#f8fafc}
.message{padding:12px;margin:8px 0;border-radius:10px;max-width:85%;overflow-wrap:anywhere;white-space:pre-wrap}
.admin{background:#e5e7eb;margin-right:auto}
.moi{background:#d6e9ff;margin-left:auto}
.date{display:block;font-size:11px;color:#555;margin-top:5px}
form{display:flex;gap:8px;padding:12px;border-top:1px solid #ddd}
textarea{flex:1;min-width:0;min-height:50px;padding:10px;resize:vertical;font:inherit}
button.envoyer{background:#003366;color:white;border:0;border-radius:5px;padding:12px;cursor:pointer}
button:disabled{opacity:.5}
#statut{font-size:13px;color:#555;padding:0 12px 12px}
.retour{display:inline-block;margin:15px;color:#003366}
@media(max-width:600px){
    .layout{grid-template-columns:1fr}
    .contacts{border-right:0;border-bottom:1px solid #ddd}
    #messages{height:350px}
    form{flex-direction:column}
}
</style>
</head>

<body>
<div class="app">
    <header>
        <h2>Messagerie</h2>
        <p>Connecté en tant que <?= htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') ?></p>
    </header>

    <div class="layout <?= $role === 'etudiant' ? 'sans-contacts' : '' ?>">
        <?php if ($role === 'surveillant'): ?>
        <aside class="contacts">
            <h3>Conversations</h3>
            <button type="button" id="adminContact" class="contact active">
                Administration
            </button>
            <div id="contacts">Chargement des surveillants...</div>
        </aside>
        <?php endif; ?>

        <section>
            <h3 id="titre" style="padding:0 15px">Administration</h3>
            <div id="messages" aria-live="polite">Chargement...</div>

            <form id="formMessage">
                <textarea id="message" maxlength="5000"
                    placeholder="Écris ton message..." required></textarea>
                <button id="bouton" class="envoyer" type="submit">Envoyer</button>
            </form>

            <div id="statut" aria-live="polite"></div>
        </section>
    </div>
</div>

<a class="retour" href="<?= htmlspecialchars($retour, ENT_QUOTES, 'UTF-8') ?>">
    ← Retour à mon espace
</a>

<script>
const csrf = <?= json_encode($_SESSION['chat_csrf']) ?>;
const role = <?= json_encode($role) ?>;

const zone = document.getElementById('messages');
const champ = document.getElementById('message');
const bouton = document.getElementById('bouton');
const statut = document.getElementById('statut');
const titre = document.getElementById('titre');

let destinataireType = 'admin';
let destinataireId = 0;
let signature = '';
let chargement = false;
let envoi = false;

async function chargerContacts() {
    if (role !== 'surveillant') return;

    try {
        const r = await fetch('chat_api.php?action=contacts', {
            cache: 'no-store'
        });

        const data = await r.json();

        if (!r.ok || !data.success) {
            throw new Error(data.message || 'Erreur de chargement.');
        }

        const conteneur = document.getElementById('contacts');
        conteneur.replaceChildren();

        data.contacts.forEach(c => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'contact';
            b.textContent = `${c.prenom} ${c.nom}`;

            b.addEventListener('click', () => {
                destinataireType = 'surveillant';
                destinataireId = Number(c.id);
                titre.textContent = `${c.prenom} ${c.nom}`;
                signature = '';

                document.querySelectorAll('.contact').forEach(x =>
                    x.classList.remove('active')
                );

                b.classList.add('active');
                zone.textContent = 'Chargement...';
                charger();
            });

            conteneur.appendChild(b);
        });

        if (!data.contacts.length) {
            conteneur.textContent = 'Aucun autre surveillant.';
        }
    } catch (e) {
        document.getElementById('contacts').textContent = e.message;
    }
}

const boutonAdmin = document.getElementById('adminContact');

if (boutonAdmin) {
    boutonAdmin.addEventListener('click', () => {
        destinataireType = 'admin';
        destinataireId = 0;
        titre.textContent = 'Administration';
        signature = '';

        document.querySelectorAll('.contact').forEach(x =>
            x.classList.remove('active')
        );

        boutonAdmin.classList.add('active');
        zone.textContent = 'Chargement...';
        charger();
    });
}

async function charger() {
    if (chargement) return;
    chargement = true;

    try {
        let url = 'chat_api.php?action=lire&type=' +
            encodeURIComponent(destinataireType);

        if (destinataireType === 'surveillant') {
            url += '&id=' + encodeURIComponent(destinataireId);
        }

        const r = await fetch(url, {cache: 'no-store'});
        const data = await r.json();

        if (!r.ok || !data.success) {
            throw new Error(data.message || 'Erreur de lecture.');
        }

        const sig = JSON.stringify(data.messages);

        if (sig !== signature) {
            const bas = zone.scrollHeight - zone.scrollTop - zone.clientHeight < 100;
            signature = sig;
            zone.replaceChildren();

            if (!data.messages.length) {
                zone.textContent = 'Aucun message dans cette conversation.';
            } else {
                data.messages.forEach(m => {
                    const div = document.createElement('div');

                    div.className = 'message ' +
                        (Number(m.est_moi) === 1 ? 'moi' : 'admin');

                    const texte = document.createElement('div');
                    texte.textContent = m.message;

                    const date = document.createElement('span');
                    date.className = 'date';
                    date.textContent = m.date_envoi;

                    div.append(texte, date);
                    zone.appendChild(div);
                });
            }

            if (bas) zone.scrollTop = zone.scrollHeight;
        }

        statut.textContent = 'Messages actualisés automatiquement.';
    } catch (e) {
        statut.textContent = e.message;
    } finally {
        chargement = false;
    }
}

document.getElementById('formMessage').addEventListener('submit', async e => {
    e.preventDefault();

    if (envoi) return;

    const texte = champ.value.trim();
    if (!texte) return;

    envoi = true;
    bouton.disabled = true;

    try {
        const form = new FormData();
        form.append('action', 'envoyer');
        form.append('message', texte);
        form.append('csrf', csrf);

        if (destinataireType === 'surveillant') {
            form.append('destinataire_id', String(destinataireId));
        }

        const r = await fetch('chat_api.php', {
            method: 'POST',
            body: form
        });

        const data = await r.json();

        if (!r.ok || !data.success) {
            throw new Error(data.message || 'Erreur d’envoi.');
        }

        champ.value = '';
        signature = '';
        await charger();
    } catch (e) {
        statut.textContent = e.message;
    } finally {
        envoi = false;
        bouton.disabled = false;
    }
});

chargerContacts();
charger();
setInterval(charger, 1000);
</script>
</body>
</html>

