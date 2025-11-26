<?php


define('STORAGE_FILE', 'tasks.json');

define('CSRF_TOKEN_NAME', 'csrf_token');


session_start();
function loadTasks() {
    try {
        if (!file_exists(STORAGE_FILE)) {
            file_put_contents(STORAGE_FILE, json_encode([]));
            return [];
        }
        
        $content = file_get_contents(STORAGE_FILE);
        if ($content === false) {
            throw new Exception('Erreur de lecture du fichier');
        }
        
        $tasks = json_decode($content, true) ?? [];
        
       
        usort($tasks, function($a, $b) {
            if ($a['done'] !== $b['done']) {
                return $a['done'] ? 1 : -1;
            }
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $tasks;
    } catch (Exception $e) {
        error_log('Erreur loadTasks: ' . $e->getMessage());
        return [];
    }
}

function saveTasks($tasks) {
    try {
        return file_put_contents(STORAGE_FILE, json_encode($tasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } catch (Exception $e) {
        error_log('Erreur saveTasks: ' . $e->getMessage());
        return false;
    }
}

function generateCsrfToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}


function validateCsrfToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && 
           hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function formatDate($dateString) {
    $date = new DateTime($dateString);
    return $date->format('d/m/Y à H:i');
}

$tasks = loadTasks();
$message = '';
$messageType = '';
$filter = $_GET['filter'] ?? 'all'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!validateCsrfToken($csrfToken)) {
        $message = 'Erreur de sécurité. Veuillez réessayer.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'];
        
        switch ($action) {
            case 'add':
                if (isset($_POST['task']) && !empty(trim($_POST['task']))) {
                    $taskText = sanitizeInput($_POST['task']);
                    $priority = isset($_POST['priority']) ? sanitizeInput($_POST['priority']) : 'medium';
                    
                    $newTask = [
                        'id' => uniqid('', true),
                        'text' => $taskText,
                        'done' => false,
                        'priority' => $priority,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $tasks[] = $newTask;
                    if (saveTasks($tasks)) {
                        $message = 'Tâche ajoutée avec succès !';
                        $messageType = 'success';
                    } else {
                        $message = 'Erreur lors de la sauvegarde.';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'edit':
                if (isset($_POST['task_id']) && isset($_POST['task_text'])) {
                    $taskId = $_POST['task_id'];
                    $taskText = sanitizeInput($_POST['task_text']);
                    $priority = isset($_POST['priority']) ? sanitizeInput($_POST['priority']) : 'medium';
                    
                    foreach ($tasks as &$task) {
                        if ($task['id'] === $taskId) {
                            $task['text'] = $taskText;
                            $task['priority'] = $priority;
                            $task['updated_at'] = date('Y-m-d H:i:s');
                            break;
                        }
                    }
                    
                    if (saveTasks($tasks)) {
                        $message = 'Tâche modifiée avec succès !';
                        $messageType = 'success';
                    }
                }
                break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['toggle']) && !empty($_GET['toggle'])) {
        $taskId = $_GET['toggle'];
        foreach ($tasks as &$task) {
            if ($task['id'] === $taskId) {
                $task['done'] = !$task['done'];
                $task['updated_at'] = date('Y-m-d H:i:s');
                $message = $task['done'] ? 'Tâche marquée comme terminée !' : 'Tâche marquée comme non terminée !';
                $messageType = 'success';
                break;
            }
        }
        saveTasks($tasks);
    }
    
    if (isset($_GET['delete']) && !empty($_GET['delete'])) {
        $taskId = $_GET['delete'];
        $initialCount = count($tasks);
        $tasks = array_filter($tasks, function($task) use ($taskId) {
            return $task['id'] !== $taskId;
        });
        
        if (count($tasks) < $initialCount) {
            saveTasks($tasks);
            $message = 'Tâche supprimée avec succès !';
            $messageType = 'success';
        }
    }

    if (isset($_GET['filter'])) {
        $filter = $_GET['filter'];
    }
}

$filteredTasks = $tasks;
if ($filter === 'pending') {
    $filteredTasks = array_filter($tasks, fn($t) => !$t['done']);
} elseif ($filter === 'completed') {
    $filteredTasks = array_filter($tasks, fn($t) => $t['done']);
}


$csrfToken = generateCsrfToken();


$totalTasks = count($tasks);
$completedTasks = count(array_filter($tasks, fn($t) => $t['done']));
$pendingTasks = $totalTasks - $completedTasks;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>To-Do List Premium</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <header class="app-header">
            <h1><i class="fas fa-tasks"></i> To-Do List Premium</h1>
            <p class="subtitle">Organisez votre journée efficacement</p>
        </header>

        <?php if (!empty($message)): ?>
            <div class="message message-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
                <button class="message-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <div class="stats-container">
            <div class="stat-card">
                <span class="stat-number"><?= $totalTasks ?></span>
                <span class="stat-label">Total</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $pendingTasks ?></span>
                <span class="stat-label">En attente</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $completedTasks ?></span>
                <span class="stat-label">Terminées</span>
            </div>
        </div>

        <form method="POST" class="task-form">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="add">
            <div class="input-group">
                <input type="text" name="task" placeholder="Quelle est votre prochaine tâche ?" required 
                       maxlength="200" class="task-input">
                <select name="priority" class="priority-select">
                    <option value="low">Faible</option>
                    <option value="medium" selected>Moyenne</option>
                    <option value="high">Élevée</option>
                </select>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-plus"></i> Ajouter
                </button>
            </div>
        </form>

        <div class="tasks-container">
            <div class="tasks-header">
                <h2>Mes Tâches</h2>
                <div class="filters">
                    <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">Toutes</a>
                    <a href="?filter=pending" class="filter-btn <?= $filter === 'pending' ? 'active' : '' ?>">En attente</a>
                    <a href="?filter=completed" class="filter-btn <?= $filter === 'completed' ? 'active' : '' ?>">Terminées</a>
                </div>
            </div>
            
            <?php if (empty($filteredTasks)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>Aucune tâche trouvée</h3>
                    <p>
                        <?php 
                        if ($filter === 'all') {
                            echo 'Commencez par ajouter votre première tâche !';
                        } elseif ($filter === 'pending') {
                            echo 'Aucune tâche en attente.';
                        } else {
                            echo 'Aucune tâche terminée.';
                        }
                        ?>
                    </p>
                </div>
            <?php else: ?>
                <ul class="tasks-list">
                    <?php foreach ($filteredTasks as $task): ?>
                        <li class="task-item <?= $task['done'] ? 'completed' : '' ?> priority-<?= $task['priority'] ?>">
                            <div class="task-content">
                                <div class="task-checkbox">
                                    <a href="?toggle=<?= $task['id'] ?>" class="toggle-btn <?= $task['done'] ? 'checked' : '' ?>">
                                        <i class="fas fa-check"></i>
                                    </a>
                                </div>
                                <div class="task-text-container">
                                    <span class="task-text"><?= $task['text'] ?></span>
                                    <div class="task-meta">
                                        <small class="task-date">
                                            <i class="far fa-clock"></i>
                                            <?= formatDate($task['created_at']) ?>
                                        </small>
                                        <?php if ($task['done']): ?>
                                            <small class="task-completed">
                                                <i class="fas fa-check-circle"></i>
                                                Terminée le <?= formatDate($task['updated_at']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="task-actions">
                                <span class="priority-badge priority-<?= $task['priority'] ?>">
                                    <?= $task['priority'] === 'high' ? 'Élevée' : ($task['priority'] === 'medium' ? 'Moyenne' : 'Faible') ?>
                                </span>
                                <a href="?delete=<?= $task['id'] ?>" class="action-btn delete-btn" 
                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette tâche ?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <script>
      setTimeout(() => {
            const messages = document.querySelectorAll('.message');
            messages.forEach(msg => {
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);

        
        document.addEventListener('DOMContentLoaded', () => {
            const taskItems = document.querySelectorAll('.task-item');
            taskItems.forEach((item, index) => {
                item.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>