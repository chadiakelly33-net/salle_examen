<?php
session_start();

if (
    !isset($_SESSION['id_admin']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: connexion.php');
    exit();
}

require_once __DIR__ . '/../connexionDB.php';
include("menu_principal.php");

if (!isset($_SESSION['chat_csrf'])) {
    $_SESSION['chat_csrf'] = bin2hex(random_bytes(32));
}

$etudiants = $bdd->query("
    SELECT id_etu AS id, nom_etu AS nom, prenom_etu AS prenom
    FROM etudiant
    ORDER BY nom_etu
")->fetchAll(PDO::FETCH_ASSOC);

$surveillants = $bdd->query("
    SELECT id_surv AS id, nom_surv AS nom, prenom_surv AS prenom
    FROM surveillant
    ORDER BY nom_surv
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chat administrateur</title>
<style>
*{box-sizing:border-box}
body{font-family:Arial;background:#f4f6f9;margin:0;padding:20px}
.app{max-width:1000px;margin:auto;background:white;border-radius:10px;overflow:hidden;box-shadow:0 3px 12px #0001}
header{background:#003366;color:white;padding:18px}
.layout{display:grid;grid-template-columns:280px minmax(0,1fr);min-height:500px}
.contacts{border-right:1px solid #ddd;padding:15px}
select,input,textarea{width:100%;padding:11px;margin:6px 0;border:1px solid #ccc;border-radius:5px;font:inherit}
#liste{max-height:350px;overflow-y:auto}
.contact{display:block;width:100%;text-align:left;padding:12px;border:0;border-bottom:1px solid #eee;background:white;cursor:pointer}
.contact.active{background:#dceaff}
.conversation{display:flex;flex-direction:column;min-width:0}
#messages{height:390px;overflow-y:auto;padding:15px;background:#f8fafc}
.message{padding:12px;margin:8px 0;border-radius:10px;max-width:85%;overflow-wrap:anywhere;white-space:pre-wrap}
.recu{background:#e5e7eb;margin-right:auto}
.envoye{background:#d6e9ff;margin-left:auto}
.date{display:block;font-size:11px;color:#555;margin-top:5px}
form{display:flex;gap:8px;padding:12px;border-top:1px solid #ddd}
form textarea{flex:1;min-width:0;resize:vertical}
button.envoyer{background:#003366;color:white;border:0;border-radius:5px;padding:12px;cursor:pointer}
button:disabled{opacity:.5}
#statut{font-size:13px;color:#555;padding:0 12px 12px}
@media(max-width:650px){.layout{grid-template-columns:1fr}.contacts{border-right:0;border-bottom:1px solid #ddd}#messages{height:330px}}
</style>
</head>
<body>
<div class="app">
<header>
    <h2>Messagerie administrateur</h2>
    <p>Communication avec les étudiants et les surveillants</p>
</header>

<div class="layout">
    <aside class="contacts">
        <label for="type">Type de contact</label>
        <select id="type">
            <option value="etudiant">Étudiants</option>
            <option value="surveillant">Surveillants</option>
        </select>

        <input id="recherche" placeholder="Rechercher un contact...">
        <div id="liste"></div>
    </aside>

    <section class="conversation">
        <h3 id="titre" style="padding:0 15px">Choisis un contact</h3>
        <div id="messages" aria-live="polite">Sélectionne un contact pour commencer.</div>

        <form id="formMessage">
            <textarea id="message" maxlength="5000" placeholder="Écrire un message..." required></textarea>
            <button class="envoyer" id="bouton" type="submit">Envoyer</button>
        </form>
        <div id="statut" aria-live="polite"></div>
    </section>
</div>
</div>

<script>
const csrf = <?= json_encode($_SESSION['chat_csrf']) ?>;
const contacts = {
    etudiant: <?= json_encode($etudiants, JSON_UNESCAPED_UNICODE) ?>,
    surveillant: <?= json_encode($surveillants, JSON_UNESCAPED_UNICODE) ?>
};

const typeSelect = document.getElementById('type');
const recherche = document.getElementById('recherche');
const liste = document.getElementById('liste');
const zone = document.getElementById('messages');
const titre = document.getElementById('titre');
const champ = document.getElementById('message');
const statut = document.getElementById('statut');
const bouton = document.getElementById('bouton');

let contact = null;
let signature = '';
let chargement = false;
let envoi = false;

function afficherContacts() {
    liste.replaceChildren();
    const terme = recherche.value.toLowerCase();

    contacts[typeSelect.value]
        .filter(c => `${c.nom} ${c.prenom}`.toLowerCase().includes(terme))
        .forEach(c => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'contact' +
                (contact && contact.id === Number(c.id) &&
                 contact.type === typeSelect.value ? ' active' : '');

            b.textContent = `${c.prenom} ${c.nom}`;
            b.addEventListener('click', () => {
                contact = {
                    id: Number(c.id),
                    type: typeSelect.value,
                    nom: `${c.prenom} ${c.nom}`
                };

                signature = '';
                titre.textContent = contact.nom;
                afficherContacts();
                chargerMessages();
            });

            liste.appendChild(b);
        });
}

async function chargerMessages() {
    if (!contact || chargement) return;

    chargement = true;
    const cible = {...contact};

    try {
        const url = `chat_api.php?action=lire&type=${encodeURIComponent(cible.type)}&id=${cible.id}`;
        const r = await fetch(url, {cache:'no-store'});
        const data = await r.json();

        if (!r.ok || !data.success) {
            throw new Error(data.message || 'Erreur de chargement.');
        }

        if (!contact || contact.id !== cible.id || contact.type !== cible.type) return;

        const sig = JSON.stringify(data.messages);

        if (sig !== signature) {
            const bas = zone.scrollHeight - zone.scrollTop - zone.clientHeight < 100;
            signature = sig;
            zone.replaceChildren();

            if (!data.messages.length) {
                zone.textContent = 'Aucun message. Écris le premier message.';
            } else {
                data.messages.forEach(m => {
                    const div = document.createElement('div');
                    div.className = 'message ' + (Number(m.est_admin) ? 'envoye' : 'recu');

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

typeSelect.addEventListener('change', () => {
    contact = null;
    signature = '';
    titre.textContent = 'Choisis un contact';
    zone.textContent = 'Sélectionne un contact pour commencer.';
    afficherContacts();
});

recherche.addEventListener('input', afficherContacts);

document.getElementById('formMessage').addEventListener('submit', async e => {
    e.preventDefault();

    if (!contact || envoi) {
        statut.textContent = 'Choisis un contact.';
        return;
    }

    const texte = champ.value.trim();
    if (!texte) return;

    envoi = true;
    bouton.disabled = true;

    try {
        const form = new FormData();
        form.append('action', 'envoyer');
        form.append('type', contact.type);
        form.append('id', String(contact.id));
        form.append('message', texte);
        form.append('csrf', csrf);

        const r = await fetch('chat_api.php', {method:'POST', body:form});
        const data = await r.json();

        if (!r.ok || !data.success) {
            throw new Error(data.message || 'Erreur d’envoi.');
        }

        champ.value = '';
        signature = '';
        await chargerMessages();
    } catch (e) {
        statut.textContent = e.message;
    } finally {
        envoi = false;
        bouton.disabled = false;
    }
});

afficherContacts();
setInterval(chargerMessages, 1000);
</script>
</body>
</html>

