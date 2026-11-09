<?php
session_start();
require_once 'db.php';

// Handle Registration
$reg_error = "";
$reg_success = "";
if (isset($_POST['register'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $password);
    if ($stmt->execute()) {
        $reg_success = "Registration successful! You can now log in.";
    } else {
        $reg_error = "Email already exists or invalid data.";
    }
}

// Handle Login
$login_error = "";
if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_name'] = $row['name'];
            header("Location: index.php");
            exit();
        } else {
            $login_error = "Invalid password!";
        }
    } else {
        $login_error = "No account found with that email.";
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    unset($_SESSION['user_id']);
    unset($_SESSION['user_name']);
    header("Location: index.php");
    exit();
}

// Handle Add Deck
if (isset($_POST['add_deck']) && isset($_SESSION['user_id'])) {
    $title = trim($_POST['deck_title']);
    $desc = trim($_POST['deck_desc']);
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO decks (user_id, title, description) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $title, $desc);
    $stmt->execute();
    header("Location: index.php");
    exit();
}

// Handle Add Card
if (isset($_POST['add_card']) && isset($_SESSION['user_id'])) {
    $deck_id = $_POST['deck_id'];
    $front = trim($_POST['front_text']);
    $back = trim($_POST['back_text']);

    $stmt = $conn->prepare("INSERT INTO flashcards (deck_id, front_text, back_text) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $deck_id, $front, $back);
    $stmt->execute();
    header("Location: index.php");
    exit();
}

// Fetch Decks and Flashcards for logged-in user
$decks = [];
$total_cards = 0;
$quiz_questions = [];

if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $res = $conn->query("SELECT * FROM decks WHERE user_id = $uid ORDER BY id DESC");
    while ($row = $res->fetch_assoc()) {
        $decks[] = $row;
    }

    $card_res = $conn->query("SELECT COUNT(f.id) as total FROM flashcards f JOIN decks d ON f.deck_id = d.id WHERE d.user_id = $uid");
    if($c_row = $card_res->fetch_assoc()) {
        $total_cards = $c_row['total'];
    }

    // Fetch all cards for Quiz Mode
    $q_res = $conn->query("SELECT f.front_text, f.back_text FROM flashcards f JOIN decks d ON f.deck_id = d.id WHERE d.user_id = $uid");
    while($q_row = $q_res->fetch_assoc()) {
        $quiz_questions[] = $q_row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StuLiving - Student Reviewer & Study Management</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 512 512%22><path fill=%22%23198754%22 d=%22M47.6 300.4L228.3 469.1c7.5 7 17.4 10.9 27.7 10.9s20.2-3.9 27.7-10.9L464.4 300.4c30.4-28.3 47.6-68 47.6-109.5v-5.8c0-85.1-61.2-155.1-143.2-155.1-42.9 0-83.6 18.6-111.4 51.2-27.8-32.6-68.5-51.2-111.4-51.2C61.2 24 0 94 0 179.1v5.8c0 41.5 17.2 81.2 47.6 109.5z%22/></svg>">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --alice-blue: #F6FAFF;
            --naples-yellow: #FFDE70;
            --powder-blue: #A3C4EB;
            --yale-blue: #173B64;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--alice-blue);
            color: var(--yale-blue);
        }

        .navbar {
            background-color: var(--yale-blue) !important;
        }

        .navbar-brand, .nav-link {
            color: var(--alice-blue) !important;
        }

        .nav-link:hover {
            color: var(--naples-yellow) !important;
        }

        .btn-custom-primary {
            background-color: var(--yale-blue);
            color: var(--alice-blue);
            border: none;
        }

        .btn-custom-primary:hover {
            background-color: #0f2744;
            color: var(--alice-blue);
        }

        .btn-custom-accent {
            background-color: var(--naples-yellow);
            color: var(--yale-blue);
            font-weight: 600;
            border: none;
        }

        .btn-custom-accent:hover {
            background-color: #f7d148;
            color: var(--yale-blue);
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(23, 59, 100, 0.08);
            background-color: #ffffff;
        }

        /* Flashcard Flip Effect */
        .flashcard-container {
            perspective: 1000px;
            width: 100%;
            height: 250px;
            cursor: pointer;
        }

        .flashcard-inner {
            position: relative;
            width: 100%;
            height: 100%;
            text-align: center;
            transition: transform 0.6s;
            transform-style: preserve-3d;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            border-radius: 12px;
        }

        .flashcard-container.flipped .flashcard-inner {
            transform: rotateY(180deg);
        }

        .flashcard-front, .flashcard-back {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            border-radius: 12px;
            font-size: 1.2rem;
        }

        .flashcard-front {
            background-color: #ffffff;
            color: var(--yale-blue);
            border: 2px solid var(--powder-blue);
        }

        .flashcard-back {
            background-color: var(--yale-blue);
            color: var(--alice-blue);
            transform: rotateY(180deg);
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                <i class="fa-solid fa-heart text-success fs-3"></i>
                <span class="fw-bold fs-4">StuLiving</span>
            </a>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav gap-3 align-items-center">
                    <li class="nav-item"><a class="nav-link active" href="#dashboard" onclick="switchTab('dashboard')"><i class="fa-solid fa-house"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="#flashcards" onclick="switchTab('flashcards')"><i class="fa-solid fa-layer-group"></i> Reviewer</a></li>
                    <li class="nav-item"><a class="nav-link" href="#pomodoro" onclick="switchTab('pomodoro')"><i class="fa-solid fa-clock"></i> Pomodoro</a></li>
                    <li class="nav-item"><a class="nav-link" href="#quiz" onclick="switchTab('quiz')"><i class="fa-solid fa-brain"></i> Quiz Mode</a></li>
                    <li class="nav-item"><a class="nav-link" href="#auth" onclick="switchTab('auth')"><i class="fa-solid fa-user-lock"></i> Login / Register</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item"><span class="text-white ms-2"><b><?php echo htmlspecialchars($_SESSION['user_name']); ?></b></span></li>
                        <li class="nav-item"><a class="btn btn-outline-light btn-sm ms-2" href="index.php?logout=true">Logout</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="container my-5">

        <!-- AUTHENTICATION TAB -->
        <div id="tab-auth" class="content-tab d-none">
            <div class="row justify-content-center">
                <div class="col-md-5 mb-4">
                    <div class="card p-4">
                        <h3 class="fw-bold mb-3" style="color: var(--yale-blue);"><i class="fa-solid fa-right-to-bracket"></i> Login</h3>
                        <?php if($login_error): ?><div class="alert alert-danger"><?php echo $login_error; ?></div><?php endif; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Email address</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" name="login" class="btn btn-custom-primary w-100 py-2">Login</button>
                        </form>
                    </div>
                </div>

                <div class="col-md-5">
                    <div class="card p-4">
                        <h3 class="fw-bold mb-3" style="color: var(--yale-blue);"><i class="fa-solid fa-user-plus"></i> Register</h3>
                        <?php if($reg_success): ?><div class="alert alert-success"><?php echo $reg_success; ?></div><?php endif; ?>
                        <?php if($reg_error): ?><div class="alert alert-danger"><?php echo $reg_error; ?></div><?php endif; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email address</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" name="register" class="btn btn-custom-accent w-100 py-2">Register</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- DASHBOARD TAB -->
        <div id="tab-dashboard" class="content-tab">
            <div class="p-5 mb-4 rounded-4 shadow-sm" style="background-color: var(--powder-blue);">
                <div class="container-fluid py-3">
                    <h1 class="display-5 fw-bold" style="color: var(--yale-blue);">
                        Welcome back<?php echo isset($_SESSION['user_name']) ? ', ' . htmlspecialchars($_SESSION['user_name']) : ', Student'; ?>! 🌱
                    </h1>
                    <p class="col-md-8 fs-5 text-secondary">Manage your flashcards, stay focused with Pomodoro, and review your study materials seamlessly.</p>
                    <button class="btn btn-custom-accent btn-lg px-4 gap-3 mt-2" onclick="switchTab('flashcards')">Manage Reviewers</button>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card p-4 text-center">
                        <i class="fa-solid fa-layer-group fa-2x mb-3" style="color: var(--yale-blue);"></i>
                        <h3><?php echo count($decks); ?> Decks</h3>
                        <p class="text-muted mb-0">Your Active Decks</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-4 text-center">
                        <i class="fa-solid fa-circle-check fa-2x mb-3 text-success"></i>
                        <h3><?php echo $total_cards; ?> Cards</h3>
                        <p class="text-muted mb-0">Total Flashcards Created</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-4 text-center">
                        <i class="fa-solid fa-fire fa-2x mb-3 text-warning"></i>
                        <h3>Streak Active</h3>
                        <p class="text-muted mb-0">Keep studying daily!</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- FLASHCARDS / REVIEWER MANAGEMENT & STUDY MODE TAB -->
        <div id="tab-flashcards" class="content-tab d-none">
            <h2 class="mb-4 fw-bold" style="color: var(--yale-blue);"><i class="fa-solid fa-layer-group"></i> Flashcard Reviewer & Study Mode</h2>
            
            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="alert alert-warning">Please <a href="#" onclick="switchTab('auth')">Login or Register</a> first to create and view your custom decks!</div>
            <?php else: ?>
                <div class="row">
                    <!-- Create Deck & Add Card Forms -->
                    <div class="col-md-4 mb-4">
                        <div class="card p-4 mb-3">
                            <h5 class="fw-bold mb-3">Create New Deck</h5>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Deck Title</label>
                                    <input type="text" name="deck_title" class="form-control" placeholder="e.g., STS Midterm" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="deck_desc" class="form-control" rows="2" placeholder="Brief description"></textarea>
                                </div>
                                <button type="submit" name="add_deck" class="btn btn-custom-primary w-100">Add Deck</button>
                            </form>
                        </div>

                        <div class="card p-4">
                            <h5 class="fw-bold mb-3">Add Flashcard</h5>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Select Deck</label>
                                    <select name="deck_id" class="form-control" required>
                                        <?php foreach($decks as $deck): ?>
                                            <option value="<?php echo $deck['id']; ?>"><?php echo htmlspecialchars($deck['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Front (Question)</label>
                                    <input type="text" name="front_text" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Back (Answer)</label>
                                    <input type="text" name="back_text" class="form-control" required>
                                </div>
                                <button type="submit" name="add_card" class="btn btn-custom-accent w-100">Add Card</button>
                            </form>
                        </div>
                    </div>

                    <!-- Display Decks and Study Mode Player -->
                    <div class="col-md-8">
                        <div class="card p-4 mb-4">
                            <h5 class="fw-bold mb-3">Your Decks List</h5>
                            <?php if(empty($decks)): ?>
                                <p class="text-muted">No decks created yet. Create a deck and add cards to start studying!</p>
                            <?php else: ?>
                                <ul class="list-group">
                                    <?php foreach($decks as $deck): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="fw-bold mb-0" style="color: var(--yale-blue);"><?php echo htmlspecialchars($deck['title']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($deck['description']); ?></small>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <!-- Study Mode Flip Card Player -->
                        <div class="card p-4 text-center">
                            <h5 class="fw-bold mb-3 text-start"><i class="fa-solid fa-book-open"></i> Study Mode (Flip Cards)</h5>
                            <div class="d-flex justify-content-between mb-3 text-muted">
                                <span>Interactive Review</span>
                                <span>Card <span id="card-index">1</span> of <span id="total-cards">1</span></span>
                            </div>
                            
                            <div class="flashcard-container mb-4" onclick="flipCardWithSound()">
                                <div class="flashcard-inner" id="card-inner">
                                    <div class="flashcard-front">
                                        <h4 id="card-front-text">Create a deck and add flashcards to begin!</h4>
                                    </div>
                                    <div class="flashcard-back">
                                        <h4 id="card-back-text">Answers will appear here.</h4>
                                    </div>
                                </div>
                            </div>
                            <p class="text-muted small mb-4"><i class="fa-solid fa-hand-pointer"></i> Click card to flip</p>

                            <div class="d-flex justify-content-center gap-3">
                                <button class="btn btn-outline-danger px-4" onclick="nextCard('Difficult')">Difficult</button>
                                <button class="btn btn-outline-warning px-4" onclick="nextCard('Okay')">Okay</button>
                                <button class="btn btn-outline-success px-4" onclick="nextCard('Easy')">Easy</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- POMODORO TIMER TAB -->
        <div id="tab-pomodoro" class="content-tab d-none">
            <h2 class="mb-4 fw-bold" style="color: var(--yale-blue);"><i class="fa-solid fa-clock"></i> Pomodoro Study Timer</h2>
            <div class="row justify-content-center">
                <div class="col-md-6 text-center">
                    <div class="card p-5">
                        <h1 id="timer-display" class="display-1 fw-bold mb-4" style="color: var(--yale-blue);">25:00</h1>
                        <p class="text-muted" id="timer-status">Focus Session</p>
                        <div class="d-flex justify-content-center gap-3 mt-3">
                            <button class="btn btn-custom-primary px-4 py-2" onclick="startTimer()"><i class="fa-solid fa-play"></i> Start</button>
                            <button class="btn btn-outline-secondary px-4 py-2" onclick="pauseTimer()"><i class="fa-solid fa-pause"></i> Pause</button>
                            <button class="btn btn-outline-danger px-4 py-2" onclick="resetTimer()"><i class="fa-solid fa-rotate-right"></i> Reset</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- QUIZ MODE TAB -->
        <div id="tab-quiz" class="content-tab d-none">
            <h2 class="mb-4 fw-bold" style="color: var(--yale-blue);"><i class="fa-solid fa-brain"></i> Quiz Mode</h2>
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card p-4">
                        <div class="d-flex justify-content-between mb-2">
                            <h5 id="quiz-progress">Question 1 of 1</h5>
                            <button class="btn btn-sm btn-outline-secondary" onclick="loadQuizQuestion()"><i class="fa-solid fa-rotate"></i> Next Question</button>
                        </div>
                        <p id="quiz-question-text" class="fs-5 fw-medium my-3">Magdagdag muna ng flashcards para masubukan ang Quiz Mode!</p>
                        <div id="quiz-options" class="d-grid gap-2">
                            <!-- Options generated dynamically -->
                        </div>
                        <div id="quiz-feedback" class="mt-3 fw-bold fs-5 text-center"></div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Audio elements for sound effects -->
    <audio id="alarm-sound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" preload="auto"></audio>
    <audio id="flip-sound" src="https://assets.mixkit.co/active_storage/sfx/2568/2568-preview.mp3" preload="auto"></audio>
    <audio id="correct-sound" src="https://assets.mixkit.co/active_storage/sfx/1435/1435-preview.mp3" preload="auto"></audio>
    <audio id="wrong-sound" src="https://assets.mixkit.co/active_storage/sfx/2955/2955-preview.mp3" preload="auto"></audio>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.content-tab').forEach(tab => tab.classList.add('d-none'));
            const target = document.getElementById('tab-' + tabId);
            if(target) target.classList.remove('d-none');
            
            document.querySelectorAll('.navbar-nav .nav-link').forEach(link => link.classList.remove('active'));
            if(event && event.currentTarget) event.currentTarget.classList.add('active');
        }

        // Dynamic flashcards loader for Study Mode
        <?php
        $js_cards = [];
        if (isset($_SESSION['user_id'])) {
            $c_res = $conn->query("SELECT f.front_text, f.back_text FROM flashcards f JOIN decks d ON f.deck_id = d.id WHERE d.user_id = $uid");
            while($c_row = $c_res->fetch_assoc()) {
                $js_cards[] = $c_row;
            }
        }
        ?>
        const studyCards = <?php echo json_encode($js_cards); ?>;
        let currentCardIndex = 0;

        function loadStudyCard() {
            if(studyCards.length > 0) {
                document.getElementById('card-index').innerText = currentCardIndex + 1;
                document.getElementById('total-cards').innerText = studyCards.length;
                document.getElementById('card-front-text').innerText = studyCards[currentCardIndex].front_text;
                document.getElementById('card-back-text').innerText = studyCards[currentCardIndex].back_text;
            }
        }

        function flipCardWithSound() {
            const container = document.querySelector('.flashcard-container');
            container.classList.toggle('flipped');
            const sound = document.getElementById('flip-sound');
            if(sound) {
                sound.currentTime = 0;
                sound.play();
            }
        }

        function nextCard(rating) {
            if(studyCards.length === 0) return;
            currentCardIndex = (currentCardIndex + 1) % studyCards.length;
            loadStudyCard();
            document.querySelector('.flashcard-container').classList.remove('flipped');
        }

        // Dynamic Quiz Mode Logic linked to user flashcards
        const quizQuestions = <?php echo json_encode($quiz_questions); ?>;
        let currentQuizIndex = 0;

        function loadQuizQuestion() {
            const questionText = document.getElementById('quiz-question-text');
            const optionsContainer = document.getElementById('quiz-options');
            const progress = document.getElementById('quiz-progress');
            const feedback = document.getElementById('quiz-feedback');
            
            feedback.innerHTML = "";
            optionsContainer.innerHTML = "";

            if (quizQuestions.length === 0) {
                questionText.innerText = "Wala pang flashcards. Gumawa muna ng Deck at magdagdag ng flashcards para magamit ang Quiz Mode!";
                progress.innerText = "Question 0 of 0";
                return;
            }

            progress.innerText = `Question ${currentQuizIndex + 1} of ${quizQuestions.length}`;
            const currentQ = quizQuestions[currentQuizIndex];
            questionText.innerText = currentQ.front_text;

            // Gather options (Correct answer + random wrong answers from other cards)
            let options = [currentQ.back_text];
            while (options.length < 3 && options.length < quizQuestions.length) {
                let randomCard = quizQuestions[Math.floor(Math.random() * quizQuestions.length)];
                if (!options.includes(randomCard.back_text)) {
                    options.push(randomCard.back_text);
                }
            }
            // Shuffle options
            options.sort(() => Math.random() - 0.5);

            options.forEach(opt => {
                const btn = document.createElement('button');
                btn.className = "btn btn-outline-primary text-start p-3";
                btn.innerText = opt;
                const isCorrect = (opt === currentQ.back_text);
                btn.onclick = function() { answerQuiz(isCorrect, btn); };
                optionsContainer.appendChild(btn);
            });

            currentQuizIndex = (currentQuizIndex + 1) % quizQuestions.length;
        }

        function answerQuiz(isCorrect, btnElement) {
            const feedback = document.getElementById('quiz-feedback');
            // Disable all options after click
            document.querySelectorAll('#quiz-options button').forEach(b => b.disabled = true);

            if (isCorrect) {
                feedback.innerHTML = '<span class="text-success"><i class="fa-solid fa-circle-check"></i> Correct! Great job. 🎉</span>';
                btnElement.classList.remove('btn-outline-primary');
                btnElement.classList.add('btn-success', 'text-white');
                const sound = document.getElementById('correct-sound');
                if(sound) { sound.currentTime = 0; sound.play(); }
            } else {
                feedback.innerHTML = '<span class="text-danger"><i class="fa-solid fa-circle-xmark"></i> Incorrect! ❌</span>';
                btnElement.classList.remove('btn-outline-primary');
                btnElement.classList.add('btn-danger', 'text-white');
                const sound = document.getElementById('wrong-sound');
                if(sound) { sound.currentTime = 0; sound.play(); }
            }
        }

        window.onload = function() {
            loadStudyCard();
            loadQuizQuestion();
        };

        // Pomodoro Timer Logic with Sound
        let timerSeconds = 25 * 60;
        let timerInterval = null;
        let isRunning = false;

        function updateTimerDisplay() {
            let minutes = Math.floor(timerSeconds / 60);
            let seconds = timerSeconds % 60;
            const display = document.getElementById('timer-display');
            if(display) {
                display.innerText = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }
        }

        function startTimer() {
            if (isRunning) return;
            isRunning = true;
            timerInterval = setInterval(() => {
                if (timerSeconds > 0) {
                    timerSeconds--;
                    updateTimerDisplay();
                } else {
                    clearInterval(timerInterval);
                    isRunning = false;
                    const alarm = document.getElementById('alarm-sound');
                    if(alarm) alarm.play();
                    alert("Pomodoro session completed! Time for a break.");
                }
            }, 1000);
        }

        function pauseTimer() {
            clearInterval(timerInterval);
            isRunning = false;
        }

        function resetTimer() {
            clearInterval(timerInterval);
            isRunning = false;
            timerSeconds = 25 * 60;
            updateTimerDisplay();
        }
    </script>
</body>
</html>