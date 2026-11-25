<?php
/**
 * Mapa de Calor - Regionalização RS
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';

$user = new User();
// Admin/Gestor OU membro da Diretoria — menu da Diretoria idêntico ao Admin
if (!$user->podeAcessarDiretoria()) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

$page_title = "Mapa de Calor RS";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-map-fill text-primary me-2"></i>Mapa de Calor RS</h1>
            <span class="text-muted">Visualização geográfica de associados e indicadores por microrregião</span>
        </div>
        <a href="<?php echo SITE_URL; ?>/regionalizacao" class="btn btn-outline-secondary">
            <i class="bi bi-table me-1"></i>Tabela
        </a>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Indicador</label>
                    <select id="indicadorMapa" class="form-select form-select-sm">
                        <option value="qtd_associados">Total Associados</option>
                        <option value="qtd_ativos">Associados Ativos</option>
                        <option value="qtd_inadimplentes">Inadimplentes</option>
                        <option value="percentual_inadimplencia">% Inadimplência</option>
                        <option value="qtd_cidades">Municípios</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Mesorregião</label>
                    <select id="mesorregiaoFiltro" class="form-select form-select-sm">
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Escala de Cores</label>
                    <select id="escalaCor" class="form-select form-select-sm">
                        <option value="YlOrRd">Amarelo-Laranja-Vermelho</option>
                        <option value="Blues">Azuis</option>
                        <option value="Greens">Verdes</option>
                        <option value="RdYlGn">Vermelho-Amarelo-Verde</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button id="btnAtualizar" class="btn btn-primary w-100">
                        <i class="bi bi-arrow-clockwise me-1"></i>Atualizar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mapa -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div id="mapaRS" style="height: 600px;"></div>
        </div>
    </div>

    <!-- Legenda -->
    <div class="card shadow-sm mt-3">
        <div class="card-body">
            <div id="legendaMapa" class="d-flex flex-wrap gap-3 align-items-center">
                <span class="fw-bold">Legenda:</span>
                <div id="legendaCores" class="d-flex gap-2 flex-wrap"></div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet CSS/JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<!-- GeoJSON das microrregiões do RS (simplificado) -->
<script>
// Centro do RS
const RS_CENTER = [-30.0346, -51.2177];
const RS_ZOOM = 7;

// Inicializar mapa
const map = L.map('mapaRS').setView(RS_CENTER, RS_ZOOM);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

let geoJsonLayer = null;
let colorScale = null;

// Carregar dados e GeoJSON
async function carregarMapa() {
    try {
        const indicador = document.getElementById('indicadorMapa').value;
        const mesorregiao = document.getElementById('mesorregiaoFiltro').value;
        const escala = document.getElementById('escalaCor').value;
        
        // Buscar dados da API
        const response = await fetch('<?php echo SITE_URL; ?>/api/regionalizacao/dados.php');
        const result = await response.json();
        
        if (!result.success) throw new Error(result.error);
        
        const dados = result.data;
        
        // Filtrar por mesorregião se selecionado
        let dadosFiltrados = dados;
        if (mesorregiao) {
            dadosFiltrados = dados.filter(d => d.mesorregiao === mesorregiao);
        }
        
        // Preparar valores para escala
        const valores = dadosFiltrados.map(d => d[indicador]).filter(v => v !== undefined);
        const min = Math.min(...valores);
        const max = Math.max(...valores);
        
        // Escala de cores
        colorScale = chroma.scale(escala).domain([min, max]);
        
        // Carregar GeoJSON das microrregiões
        // Usando um GeoJSON simplificado do IBGE
        await carregarGeoJSON(dadosFiltrados, indicador);
        
        // Atualizar legenda
        atualizarLegenda(indicador, min, max, escala);
        
    } catch (error) {
        console.error('Erro ao carregar mapa:', error);
        alert('Erro ao carregar dados do mapa: ' + error.message);
    }
}

// GeoJSON simplificado das microrregiões do RS
// Em produção, usar arquivo GeoJSON completo do IBGE
const MICRORREGIOES_GEOJSON = {
    "type": "FeatureCollection",
    "features": [
        // As geometrias reais seriam carregadas de um arquivo .geojson
        // Aqui estamos criando círculos baseados no centroide de cada microrregião
    ]
};

async function carregarGeoJSON(dados, indicador) {
    // Remover layer anterior
    if (geoJsonLayer) {
        map.removeLayer(geoJsonLayer);
    }
    
    // Criar layer com círculos (placeholder - ideal usar GeoJSON real)
    const features = dados.map(d => {
        // Centroides aproximados das microrregiões (em produção usar GeoJSON real)
        const centroides = {
            'Porto Alegre': [-30.0346, -51.2177],
            'Canoas': [-29.9178, -51.1836],
            'Novo Hamburgo': [-29.6783, -51.1306],
            'São Jerônimo': [-29.9583, -51.7222],
            'Gramado-Canela': [-29.3764, -50.8742],
            'Osório': [-29.8886, -50.2708],
            'Tramandaí': [-29.9872, -50.1333],
            'Vacaria': [-28.5122, -50.9339],
            'Caxias do Sul': [-29.1686, -51.1794],
            'Guaporé': [-28.8456, -51.8903],
            'Veranópolis': [-28.9381, -51.5464],
            'São Marcos': [-28.9656, -51.0667],
            'Soledade': [-28.8183, -52.5103],
            'Passo Fundo': [-28.2628, -52.4067],
            'Carazinho': [-28.2839, -52.7819],
            'Não-Me-Toque': [-28.4708, -52.9658],
            'Cerro Largo': [-28.1458, -54.7389],
            'Santa Maria': [-29.6842, -53.8069],
            'Santiago': [-29.1889, -54.8608],
            'Restinga Seca': [-29.8069, -53.3861],
            'Cachoeira do Sul': [-30.0392, -52.8939],
            'Cruz Alta': [-28.6386, -53.6069],
            'Ijuí': [-28.3878, -53.9147],
            'Santo Ângelo': [-28.2992, -54.2631],
            'São Luiz Gonzaga': [-28.4072, -54.9603],
            'Campanha Ocidental': [-30.4567, -55.7678],
            'Campanha Central': [-30.9042, -54.3122],
            'Campanha Meridional': [-31.3317, -53.4372],
            'Serra do Sudeste': [-31.5583, -53.2078],
            'Pelotas': [-31.7653, -52.3375],
            'Rio Grande': [-32.0350, -52.0986],
            'Três Passos': [-27.4536, -53.9317],
            'Santa Rosa': [-27.8708, -54.4814],
            'São Luiz Gonzaga': [-28.4072, -54.9603],
            'Ijuí': [-28.3878, -53.9147]
        };
        
        const coords = centroides[d.microrregiao] || RS_CENTER;
        const valor = d[indicador] || 0;
        const cor = colorScale ? colorScale(valor).hex() : '#888';
        const raio = Math.max(8, Math.sqrt(valor) * 3);
        
        return {
            type: 'Feature',
            properties: {
                microrregiao: d.microrregiao,
                mesorregiao: d.mesorregiao,
                valor: valor,
                qtd_associados: d.qtd_associados,
                qtd_ativos: d.qtd_ativos,
                qtd_inadimplentes: d.qtd_inadimplentes,
                qtd_cidades: d.qtd_cidades,
                percentual_inadimplencia: d.percentual_inadimplencia
            },
            geometry: {
                type: 'Point',
                coordinates: [coords[1], coords[0]]
            }
        };
    });
    
    const geojson = {
        type: 'FeatureCollection',
        features: features
    };
    
    geoJsonLayer = L.geoJSON(geojson, {
        pointToLayer: function(feature, latlng) {
            return L.circleMarker(latlng, {
                radius: Math.max(8, Math.sqrt(feature.properties.valor) * 4),
                fillColor: colorScale ? colorScale(feature.properties.valor).hex() : '#888',
                color: '#fff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            });
        },
        onEachFeature: function(feature, layer) {
            const p = feature.properties;
            const popupContent = `
                <div class="p-2" style="min-width: 200px;">
                    <h6 class="mb-2"><i class="bi bi-geo-alt-fill text-primary"></i> ${p.microrregiao}</h6>
                    <small class="text-muted">${p.mesorregiao}</small>
                    <hr class="my-2">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="fw-bold">${p.qtd_cidades}</div>
                            <small class="text-muted">Municípios</small>
                        </div>
                        <div class="col-6">
                            <div class="fw-bold">${p.qtd_associados}</div>
                            <small class="text-muted">Associados</small>
                        </div>
                        <div class="col-6">
                            <div class="fw-bold text-success">${p.qtd_ativos}</div>
                            <small class="text-muted">Ativos</small>
                        </div>
                        <div class="col-6">
                            <div class="fw-bold text-danger">${p.qtd_inadimplentes}</div>
                            <small class="text-muted">Inadimplentes</small>
                        </div>
                        <div class="col-12 mt-2">
                            <div class="fw-bold">${p.percentual_inadimplencia}%</div>
                            <small class="text-muted">Inadimplência</small>
                        </div>
                    </div>
                </div>
            `;
            layer.bindPopup(popupContent);
        }
    }).addTo(map);
    
    // Ajustar zoom para mostrar todos os marcadores
    if (geoJsonLayer.getLayers().length > 0) {
        map.fitBounds(geoJsonLayer.getBounds(), { padding: [20, 20] });
    }
}

function atualizarLegenda(indicador, min, max, escala) {
    const labels = ['Muito Baixo', 'Baixo', 'Médio', 'Alto', 'Muito Alto'];
    const steps = 5;
    const stepSize = (max - min) / (steps - 1);
    
    let html = '';
    for (let i = 0; i < steps; i++) {
        const valor = min + (i * stepSize);
        const cor = chroma.scale(escala).domain([min, max])(valor).hex();
        html += `
            <div class="d-flex align-items-center gap-2">
                <span style="width: 20px; height: 12px; background: ${cor}; border-radius: 2px;"></span>
                <small>${labels[i]} (${valor.toFixed(1)})</small>
            </div>
        `;
    }
    
    document.getElementById('legendaCores').innerHTML = html;
}

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    // Carregar chroma.js para escalas de cores
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/chroma-js@2.4.2/chroma.min.js';
    script.onload = function() {
        carregarMapa();
        
        // Event listeners
        document.getElementById('btnAtualizar').addEventListener('click', carregarMapa);
        document.getElementById('indicadorMapa').addEventListener('change', carregarMapa);
        document.getElementById('mesorregiaoFiltro').addEventListener('change', carregarMapa);
        document.getElementById('escalaCor').addEventListener('change', carregarMapa);
    };
    document.head.appendChild(script);
    
    // Preencher mesorregiões no filtro
    fetch('<?php echo SITE_URL; ?>/api/regionalizacao/dados.php')
        .then(r => r.json())
        .then(r => {
            if (r.success) {
                const mesorregioes = [...new Set(r.data.map(d => d.mesorregiao))].sort();
                const select = document.getElementById('mesorregiaoFiltro');
                mesorregioes.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m;
                    opt.textContent = m;
                    select.appendChild(opt);
                });
            }
        });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>