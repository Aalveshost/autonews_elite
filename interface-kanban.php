<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_slots = $wpdb->prefix . 'autonews_slots';

// Ações (Salvar/Editar/Excluir)
if (isset($_POST['save_slot'])) {
    $data = [
        'day_of_week' => $_POST['day'],
        'hour' => $_POST['hour'],
        'category_id' => $_POST['cat'],
        'search_query' => $_POST['query'],
        'custom_prompt' => $_POST['prompt']
    ];
    if (!empty($_POST['slot_id'])) {
        $wpdb->update($table_slots, $data, ['id' => $_POST['slot_id']]);
    } else {
        $wpdb->insert($table_slots, $data);
    }
}
if (isset($_GET['delete'])) {
    $wpdb->delete($table_slots, ['id' => $_GET['delete']]);
}

$slots = $wpdb->get_results("SELECT * FROM $table_slots ORDER BY hour ASC");
$categories = get_categories(['hide_empty' => 0]);
$days = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];
?>

<style>
    .elite-card { background: #000; padding: 12px; border-radius: 8px; margin-bottom: 10px; border: 1px solid #333; position: relative; cursor: pointer; transition: 0.2s; }
    .elite-card:hover { border-color: #bef264; }
    .log-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.8rem; }
    .log-table th, .log-table td { text-align: left; padding: 12px; border-bottom: 1px solid #27272a; }
    .status-success { color: #bef264; font-weight: bold; }
    .status-error { color: #ef4444; font-weight: bold; }
</style>

<div class="wrap" style="background: #09090b; color: #fff; padding: 25px; border-radius: 12px; font-family: 'Inter', sans-serif; min-height: 800px;">
    <h1 style="color: #bef264; margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 15px;">
            ⚡ AutoNews Elite <span style="font-size: 0.7rem; background: #27272a; color: #a1a1aa; padding: 4px 10px; border-radius: 20px;">v1.3</span>
        </div>
        <button id="btn-test-conn" onclick="testConnection()" style="font-size: 0.8rem; background: #27272a; border: 1px solid #3f3f46; color: #fff; padding: 10px 20px; border-radius: 8px; cursor: pointer; transition: 0.3s;">🔌 Testar Conexão</button>
    </h1>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .swal2-popup-dark { background: #18181b !important; color: #fff !important; border: 1px solid #27272a !important; }
        .swal2-title, .swal2-html-container { color: #fff !important; }
    </style>

    <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 15px;">
        <?php foreach ($days as $num => $label): ?>
            <div style="background: #18181b; border-radius: 10px; padding: 15px; border: 1px solid #27272a;">
                <h3 style="color: #a1a1aa; border-bottom: 1px solid #27272a; padding-bottom: 10px; font-size: 1rem;"><?php echo $label; ?></h3>
                
                <div style="margin: 15px 0; min-height: 50px;">
                    <?php 
                    foreach ($slots as $slot) {
                        if ($slot->day_of_week == $num) {
                            $cat_name = get_cat_name($slot->category_id);
                            $js_data = esc_attr(json_encode($slot));
                            echo "<div class='elite-card' onclick='editSlot($js_data)'>";
                            echo "<strong style='color: #bef264; font-size: 0.85rem;'>[$slot->hour] $cat_name</strong><br>";
                            echo "<span style='color: #888; font-size: 0.7rem; display: block; margin-top: 5px;'>🔍 ".esc_html($slot->search_query)."</span>";
                            echo "<a href='?page=autonews-elite&delete=$slot->id' style='position:absolute; top:8px; right:8px; color:#ef4444; font-size:0.6rem; text-decoration:none;' onclick='event.stopPropagation();'>✕</a>";
                            echo "</div>";
                        }
                    }
                    ?>
                </div>
                <button onclick="openSlotModal(<?php echo $num; ?>)" style="width: 100%; background: #27272a; border: 1px dashed #3f3f46; color: #a1a1aa; padding: 8px; border-radius: 6px; cursor: pointer; font-size: 0.75rem;">+ Add Slot</button>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- LOG DE EXECUÇÃO -->
    <div style="margin-top: 50px; background: #18181b; padding: 25px; border-radius: 12px; border: 1px solid #27272a;">
        <h2 style="color: #fff; margin-bottom: 20px; font-size: 1.2rem; display: flex; align-items: center; justify-content: space-between;">
            📋 Log de Execuções Recentes
            <button onclick="loadLogs(1)" style="font-size: 0.7rem; background: transparent; border: 1px solid #27272a; color: #a1a1aa; padding: 5px 12px; border-radius: 5px; cursor: pointer;">🔄 Atualizar Log</button>
        </h2>
        <div id="log-container">
            <table class="log-table">
                <thead><tr><th>Data/Hora</th><th>Título / Mensagem</th><th>Status</th><th>Link</th></tr></thead>
                <tbody id="log-body"><tr><td colspan="4" style="text-align:center; padding:40px; color:#555;">Carregando histórico...</td></tr></tbody>
            </table>
            <div id="log-pagination" style="margin-top: 20px; text-align: right; display: flex; gap: 5px; justify-content: flex-end;"></div>
        </div>
    </div>
</div>

<!-- Modal de Cadastro/Edição -->
<div id="slotModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:9999; align-items:center; justify-content:center; backdrop-filter: blur(4px);">
    <div style="background:#18181b; padding:35px; border-radius:15px; width:450px; border: 1px solid #3f3f46; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <h2 id="modal-title" style="color:#fff; margin-bottom:25px; font-size: 1.4rem;">Adicionar Agendamento</h2>
        <form method="post">
            <input type="hidden" name="day" id="modal_day">
            <input type="hidden" name="slot_id" id="modal_id">
            
            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                <div style="flex: 1;">
                    <label style="color:#a1a1aa; display:block; margin-bottom:5px; font-size: 0.8rem;">Horário:</label>
                    <input type="time" name="hour" id="modal_hour" required style="width:100%; background:#09090b; border:1px solid #27272a; color:#fff; padding:10px; border-radius: 8px;">
                </div>
                <div style="flex: 1;">
                    <label style="color:#a1a1aa; display:block; margin-bottom:5px; font-size: 0.8rem;">Categoria WP:</label>
                    <select name="cat" id="modal_cat" style="width:100%; background:#09090b; border:1px solid #27272a; color:#fff; padding:10px; border-radius: 8px;">
                        <?php foreach($categories as $cat) echo "<option value='$cat->term_id'>$cat->name</option>"; ?>
                    </select>
                </div>
            </div>

            <label style="color:#a1a1aa; display:block; margin-bottom:5px; font-size: 0.8rem;">Busca (Google News):</label>
            <textarea name="query" id="modal_query" required placeholder="Ex: tecnologia OR gadgets -fofoca when:1d" style="width:100%; background:#09090b; border:1px solid #27272a; color:#fff; padding:12px; border-radius: 8px; margin-bottom: 15px; min-height: 80px;"></textarea>

            <label style="color:#a1a1aa; display:block; margin-bottom:5px; font-size: 0.8rem;">Prompt Customizado (IA):</label>
            <textarea name="prompt" id="modal_prompt" placeholder="Ex: Escreva com tom sério e focado em economia." style="width:100%; background:#09090b; border:1px solid #27272a; color:#fff; padding:12px; border-radius: 8px; margin-bottom: 25px; min-height: 80px;"></textarea>

            <button type="submit" name="save_slot" style="width:100%; background:#bef264; color:#000; border:none; padding:14px; border-radius:10px; font-weight:700; cursor:pointer; font-size: 1rem;">Salvar no Kanban</button>
            <button type="button" onclick="closeSlotModal()" style="width:100%; background:transparent; color:#888; border:none; margin-top:12px; cursor:pointer; font-size: 0.9rem;">Cancelar</button>
        </form>
    </div>
</div>

<script>
function openSlotModal(day) {
    document.getElementById('modal-title').innerText = "Adicionar Agendamento";
    document.getElementById('modal_day').value = day;
    document.getElementById('modal_id').value = "";
    document.getElementById('modal_hour').value = "12:00";
    document.getElementById('modal_query').value = "";
    document.getElementById('modal_prompt').value = "";
    document.getElementById('slotModal').style.display = 'flex';
}

function editSlot(data) {
    document.getElementById('modal-title').innerText = "Editar Agendamento";
    document.getElementById('modal_day').value = data.day_of_week;
    document.getElementById('modal_id').value = data.id;
    document.getElementById('modal_hour').value = data.hour;
    document.getElementById('modal_cat').value = data.category_id;
    document.getElementById('modal_query').value = data.search_query;
    document.getElementById('modal_prompt').value = data.custom_prompt;
    document.getElementById('slotModal').style.display = 'flex';
}

function closeSlotModal() { document.getElementById('slotModal').style.display = 'none'; }

async function testConnection() {
    const btn = document.getElementById('btn-test-conn');
    const originalText = btn.innerText;
    btn.innerText = '⌛ Testando...';
    btn.disabled = true;

    try {
        const response = await fetch(`${ajaxurl}?action=test_autonews_connection`);
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({ icon: 'success', title: 'Conexão OK!', text: result.data.message, customClass: { popup: 'swal2-popup-dark' } });
        } else {
            Swal.fire({ icon: 'error', title: 'Falha na Conexão', text: result.data.message, customClass: { popup: 'swal2-popup-dark' } });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro Crítico', text: e.message, customClass: { popup: 'swal2-popup-dark' } });
    } finally {
        btn.innerText = originalText;
        btn.disabled = false;
    }
}

async function loadLogs(page = 1) {
    const body = document.getElementById('log-body');
    const pagination = document.getElementById('log-pagination');
    
    try {
        const response = await fetch(`${ajaxurl}?action=get_autonews_logs&paged=${page}`);
        const result = await response.json();
        
        if (result.success) {
            let html = '';
            result.data.logs.forEach(log => {
                const date = new Date(log.time).toLocaleString();
                const statusClass = log.status === 'success' ? 'status-success' : 'status-error';
                html += `<tr>
                    <td>${date}</td>
                    <td>${log.title}</td>
                    <td class="${statusClass}">${log.status.toUpperCase()}</td>
                    <td>${log.post_id ? `<a href="post.php?post=${log.post_id}&action=edit" style="color:var(--primary);" target="_blank">Ver Rascunho ↗</a>` : '-'}</td>
                </tr>`;
            });
            body.innerHTML = html || '<tr><td colspan="4" style="text-align:center; padding:40px; color:#555;">Nenhum log encontrado.</td></tr>';
            
            // Paginação
            let pagHtml = '';
            for(let i=1; i<=result.data.pages; i++) {
                const active = i === page ? 'background:#bef264; color:#000;' : 'background:#27272a; color:#fff;';
                pagHtml += `<button onclick="loadLogs(${i})" style="border:none; padding:5px 10px; border-radius:4px; cursor:pointer; ${active}">${i}</button>`;
            }
            pagination.innerHTML = pagHtml;
        }
    } catch (e) { console.error(e); }
}

// Carregar logs ao abrir e a cada 60 segundos
loadLogs(1);
setInterval(() => loadLogs(1), 60000);
</script>
