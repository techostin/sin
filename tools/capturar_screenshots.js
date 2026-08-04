const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = 'http://localhost/app';
const EMAIL = 'biel@gmail.com';
const SENHA = 'cibulski@12';
const OUTPUT_DIR = path.join(__dirname, '..', 'img', 'manual');

// Páginas a capturar: [nome_arquivo, url]
const PAGINAS = [
    ['02-dashboard', '/'],
    ['03-caderno-dashboard', '/caderno-campo/dashboard'],
    ['04-propriedades', '/caderno-campo/propriedades'],
    ['05-propriedade-novo', '/caderno-campo/propriedade/novo'],
    ['06-lavouras', '/caderno-campo/lavouras'],
    ['07-insumos', '/caderno-campo/insumos'],
    ['08-produtos', '/caderno-campo/produtos'],
    ['09-lotes', '/caderno-campo/lotes'],
    ['10-atividades', '/caderno-campo/atividades-diarias'],
    ['11-ocorrencias', '/caderno-campo/ocorrencias'],
    ['12-associados', '/associados'],
    ['13-associacoes', '/associacoes'],
    ['14-certificacoes', '/certificacoes'],
    ['15-planos-manejo', '/planos-manejo'],
    ['16-relatorios', '/relatorios'],
    ['17-eventos', '/eventos'],
    ['18-usuarios', '/usuarios'],
    ['19-contas', '/contas'],
    ['20-fornecedor', '/fornecedor'],
    ['21-meu-perfil', '/meu-perfil'],
    ['22-visitas', '/visitas-tecnicas'],
    ['23-roteiros', '/roteiros-inspecao'],
    ['24-nao-conformidades', '/nao-conformidades'],
    ['25-relatorios-admin', '/relatorios-admin'],
];

(async () => {
    if (!fs.existsSync(OUTPUT_DIR)) {
        fs.mkdirSync(OUTPUT_DIR, { recursive: true });
    }

    const browser = await chromium.launch({
        headless: true,
        args: ['--window-size=1440,900']
    });
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 }
    });
    const page = await context.newPage();

    // Capturar página de login ANTES de logar
    try {
        console.log('Capturando tela de login...');
        await page.goto(BASE_URL + '/login', { waitUntil: 'networkidle', timeout: 30000 });
        await page.waitForTimeout(2500);
        await page.screenshot({ path: path.join(OUTPUT_DIR, '01-login.png'), fullPage: false });
        console.log('Capturado: 01-login.png');
    } catch (e) {
        console.error('Falha ao capturar login: ' + e.message);
    }

    // Realizar login
    try {
        console.log('Realizando login...');
        await page.goto(BASE_URL + '/login', { waitUntil: 'networkidle', timeout: 30000 });
        await page.fill('#email', EMAIL);
        await page.fill('#senha', SENHA);
        await page.click('#btn-entrar');
        await page.waitForTimeout(5000);

        // Verificar se houve redirecionamento
        const currentUrl = page.url();
        console.log('URL após login: ' + currentUrl);
    } catch (e) {
        console.error('Erro no login: ' + e.message);
    }

    // Capturar as demais páginas
    for (const [nome, url] of PAGINAS) {
        try {
            console.log(`Capturando: ${url}`);
            await page.goto(BASE_URL + url, { waitUntil: 'networkidle', timeout: 30000 });
            await page.waitForTimeout(2500);

            // Fechar modais se houver
            await page.evaluate(() => {
                document.querySelectorAll('.modal.show').forEach(m => {
                    const closeBtn = m.querySelector('.btn-close, [data-bs-dismiss="modal"]');
                    if (closeBtn) closeBtn.click();
                });
            }).catch(() => {});

            await page.waitForTimeout(500);
            await page.screenshot({ path: path.join(OUTPUT_DIR, nome + '.png'), fullPage: true });
            console.log(`Capturado: ${nome}.png`);
        } catch (e) {
            console.error(`Falha ao capturar ${url}: ${e.message}`);
        }
    }

    await browser.close();
    console.log('Captura de screenshots concluída!');
    console.log('Pasta de saída: ' + OUTPUT_DIR);
})();
