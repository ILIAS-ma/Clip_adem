/**
 * Enregistre les vidéos de l'espace formation en pilotant le vrai site.
 *
 * Chaque parcours est joué dans un navigateur, avec un sous-titre et un
 * curseur visible, puis la vidéo est déposée là où la leçon la cherche :
 * storage/app/public/formation/{identifiant}.webm. Quand l'interface change,
 * on relance le script et toutes les vidéos sont à jour — c'est tout
 * l'intérêt face à un tournage à la main.
 *
 * Prérequis : le site tourne (php artisan serve) avec ses assets (npm run dev
 * ou npm run build), et un compte clippeur de démonstration existe
 * (php artisan demo:accounts).
 *
 *   node scripts/formation/record.mjs
 *   node scripts/formation/record.mjs --only=lier-ses-comptes
 *   node scripts/formation/record.mjs --base=http://127.0.0.1:8000 --email=clippeur@clip.test --password=password
 *
 * Le navigateur utilisé est Microsoft Edge (installé d'office sous Windows) :
 * playwright-core ne télécharge aucun navigateur. Ailleurs, passer
 * --channel=chrome.
 */

import { chromium } from 'playwright-core';
import { mkdir, rename, rm } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { tmpdir } from 'node:os';

const args = Object.fromEntries(
    process.argv.slice(2).map((arg) => {
        const [key, ...value] = arg.replace(/^--/, '').split('=');
        return [key, value.join('=') || true];
    }),
);

const BASE = (args.base ?? 'http://127.0.0.1:8000').replace(/\/$/, '');
const EMAIL = args.email ?? 'clippeur@clip.test';
const PASSWORD = args.password ?? 'password';
const CHANNEL = args.channel ?? 'msedge';
const OUTPUT = resolve(args.out ?? 'storage/app/public/formation');
const SIZE = { width: 1280, height: 720 };

/*
 * Habillage injecté dans chaque page : un bandeau de sous-titre et un curseur
 * qui suit la souris. Le curseur réel n'apparaît pas dans un enregistrement,
 * et sans lui on ne sait pas où regarder.
 */
const OVERLAY = () => {
    const install = () => {
        if (document.getElementById('__tuto-caption')) return;

        const style = document.createElement('style');
        style.textContent = `
            #__tuto-caption { position: fixed; left: 50%; bottom: 28px; transform: translateX(-50%);
                max-width: 82%; padding: 14px 22px; border-radius: 14px; z-index: 2147483647;
                background: rgba(8, 10, 6, .88); color: #f4f7ee; font: 600 20px/1.35 system-ui, sans-serif;
                box-shadow: 0 10px 40px rgba(0,0,0,.45); border: 1px solid rgba(147,206,46,.55);
                text-align: center; transition: opacity .25s; opacity: 0; pointer-events: none; }
            #__tuto-caption.on { opacity: 1; }
            #__tuto-cursor { position: fixed; left: 0; top: 0; width: 22px; height: 22px; z-index: 2147483647;
                border-radius: 50%; background: rgba(147,206,46,.35); border: 2px solid #93CE2E;
                transform: translate(-50%, -50%); pointer-events: none; transition: width .15s, height .15s; }
            #__tuto-cursor.down { width: 34px; height: 34px; background: rgba(147,206,46,.6); }
            .__tuto-highlight { outline: 3px solid #93CE2E !important; outline-offset: 4px !important;
                border-radius: 10px; transition: outline-color .2s; }
        `;
        document.head.appendChild(style);

        const caption = document.createElement('div');
        caption.id = '__tuto-caption';
        document.body.appendChild(caption);

        const cursor = document.createElement('div');
        cursor.id = '__tuto-cursor';
        cursor.style.left = '-50px';
        document.body.appendChild(cursor);

        document.addEventListener('mousemove', (e) => {
            cursor.style.left = e.clientX + 'px';
            cursor.style.top = e.clientY + 'px';
        }, true);
        document.addEventListener('mousedown', () => cursor.classList.add('down'), true);
        document.addEventListener('mouseup', () => cursor.classList.remove('down'), true);

        window.__tutoCaption = (text) => {
            caption.textContent = text || '';
            caption.classList.toggle('on', Boolean(text));
        };
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', install);
    } else {
        install();
    }
};

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** Petite boîte à outils pour écrire un parcours lisiblement. */
function director(page) {
    let mouse = { x: SIZE.width / 2, y: SIZE.height / 2 };

    const api = {
        async go(path) {
            await page.goto(BASE + path, { waitUntil: 'load' });
            await page.evaluate(() => window.__tutoCaption?.(''));
            await sleep(600);
        },

        async say(text, ms = 3200) {
            await page.evaluate((t) => window.__tutoCaption?.(t), text);
            await sleep(ms);
        },

        async quiet() {
            await page.evaluate(() => window.__tutoCaption?.(''));
        },

        /** Amène le curseur sur un élément, le souligne, sans cliquer. */
        async point(locator, ms = 1400) {
            const target = typeof locator === 'string' ? page.getByText(locator, { exact: false }).first() : locator;

            try {
                await target.scrollIntoViewIfNeeded({ timeout: 3000 });
                const box = await target.boundingBox();
                if (!box) return false;

                const to = { x: box.x + box.width / 2, y: box.y + box.height / 2 };
                await page.mouse.move(to.x, to.y, { steps: 25 });
                mouse = to;

                await target.evaluate((el) => el.classList.add('__tuto-highlight'));
                await sleep(ms);
                await target.evaluate((el) => el.classList.remove('__tuto-highlight'));

                return true;
            } catch {
                return false;
            }
        },

        async click(locator) {
            const target = typeof locator === 'string' ? page.getByText(locator, { exact: false }).first() : locator;

            if (await api.point(target, 500)) {
                await page.mouse.down();
                await sleep(120);
                await page.mouse.up();
                await target.click({ timeout: 3000 }).catch(() => {});
                await page.waitForLoadState('load').catch(() => {});
                await sleep(800);
                return true;
            }

            return false;
        },

        async scroll(pixels, ms = 1200) {
            await page.mouse.wheel(0, pixels);
            await sleep(ms);
        },

        async firstCampaignPath() {
            await api.go('/campagnes');
            const href = await page.locator('a[href*="/campagnes/"]').first().getAttribute('href').catch(() => null);

            return href ? new URL(href, BASE).pathname : null;
        },
    };

    return api;
}

/*
 * Les parcours, un par leçon. Les leçons sans démonstration à l'écran (les
 * formats, les règles) restent en texte : filmer une page pour la filmer
 * n'apprend rien.
 */
const SCENES = {
    async bienvenue(d) {
        await d.go('/dashboard');
        await d.say('Bienvenue ! Voici votre tableau de bord : votre solde, votre niveau et vos clips.');
        await d.scroll(400);
        await d.say('Le principe : vous publiez sur votre compte, chaque tranche de 1 000 vues vous rapporte.');
        await d.go('/formation');
        await d.say('Chaque leçon couvre une étape. Suivez-les dans l’ordre, en quelques minutes.');
    },

    async 'lier-ses-comptes'(d) {
        await d.go('/mes-comptes');
        await d.say('Première étape : lier votre compte TikTok, YouTube ou Instagram.');
        // « Lier un compte » sans compte, « Lier un autre compte » sinon.
        await d.point(d.page.getByText(/Lier un (autre )?compte/).first(), 1800);
        await d.say('Acceptez toutes les permissions : sans l’accès aux statistiques, aucune vue ne remonte.');
    },

    async 'choisir-une-campagne'(d) {
        const path = await d.firstCampaignPath();
        await d.say('Les campagnes ouvertes, avec leur cachet pour 1 000 vues et leur budget restant.');

        if (path) {
            await d.click(d.page.locator(`a[href$="${path}"]`).first());
            await d.say('La fiche : le brief, la matière première à télécharger, et les règles de la campagne.');
            await d.scroll(500);
            await d.point('Clips minimum');
            await d.point('Prime de lancement');
            await d.say('Lisez les garde-fous avant de publier : vues minimum, clips minimum, plafond.');
            await d.point('Rejoindre la campagne', 1800);
            await d.say('Un clic pour rejoindre. Certaines campagnes valident les participations à la main.');
        }
    },

    async 'soumettre-un-clip'(d) {
        const path = await d.firstCampaignPath();

        if (path) {
            await d.go(path);
            await d.say('Publiez d’abord la vidéo sur votre compte lié, puis revenez sur la campagne.');
            await d.point('Soumettre', 1800);
            await d.say('Collez le lien de la publication : c’est tout. La vérification est automatique.');
        }

        await d.go('/mes-clips');
        await d.say('Dans « Mes clips », suivez l’état de chacun : en attente, validé ou refusé avec son motif.');
    },

    async 'suivre-ses-gains'(d) {
        await d.go('/mes-clips');
        await d.say('Les vues sont relevées toutes les 30 minutes les deux premiers jours.');
        await d.go('/revenus');
        await d.say('« Revenus » : votre solde disponible et l’historique de vos retraits.');
        await d.go('/classement');
        await d.say('Le classement : cette semaine, sur la saison, depuis toujours et par catégorie.');
        await d.scroll(700);
    },

    async 'retirer-ses-gains'(d) {
        await d.go('/revenus/paiement');
        await d.say('Renseignez où être payé : PayPal ou IBAN.');
        await d.scroll(700);
        await d.point(d.page.locator('#statut-legal'), 1600);
        await d.say('Sans statut, vos retraits sont plafonnés sur l’année. Auto-entrepreneur ? Déclarez votre SIRET.');
        await d.go('/revenus');
        await d.point('Demander le retrait', 1800);
        await d.say('Dès 20 €, demandez votre retrait. Les petits montants sont validés automatiquement.');
    },
};

async function login(browser) {
    const context = await browser.newContext({ viewport: SIZE, locale: 'fr-FR' });
    const page = await context.newPage();

    await page.goto(BASE + '/login', { waitUntil: 'load' });
    await page.fill('#email', EMAIL);
    await page.fill('#password', PASSWORD);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 15000 }),
        page.getByRole('button', { name: 'Se connecter' }).click(),
    ]);

    const state = await context.storageState();
    await context.close();

    return state;
}

async function record(browser, state, slug) {
    const scratch = join(tmpdir(), `formation-${slug}-${Date.now()}`);
    const context = await browser.newContext({
        viewport: SIZE,
        locale: 'fr-FR',
        storageState: state,
        recordVideo: { dir: scratch, size: SIZE },
    });
    await context.addInitScript(OVERLAY);

    const page = await context.newPage();
    const d = director(page);
    d.page = page;

    await SCENES[slug](d);
    await d.quiet();
    await sleep(500);

    const video = page.video();
    await context.close();

    const target = join(OUTPUT, `${slug}.webm`);
    await mkdir(OUTPUT, { recursive: true });
    if (existsSync(target)) await rm(target);
    await rename(await video.path(), target);
    await rm(scratch, { recursive: true, force: true });

    return target;
}

const slugs = args.only ? String(args.only).split(',') : Object.keys(SCENES);
const unknown = slugs.filter((s) => !SCENES[s]);

if (unknown.length) {
    console.error(`Parcours inconnu : ${unknown.join(', ')}. Disponibles : ${Object.keys(SCENES).join(', ')}`);
    process.exit(1);
}

const browser = await chromium.launch({ channel: CHANNEL, headless: !args.headed });

try {
    const state = await login(browser);

    for (const slug of slugs) {
        process.stdout.write(`• ${slug}… `);
        const file = await record(browser, state, slug);
        console.log(`ok → ${file}`);
    }
} finally {
    await browser.close();
}
