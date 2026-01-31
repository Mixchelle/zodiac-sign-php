<?php
include('layouts/header.php');

/**
 * Segurança básica: sempre escapar texto que vai pro HTML
 */
function e($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Converte "dd/mm" + ano -> DateTime
 */
function brDayMonthToDate($dayMonth, $year) {
  $parts = explode('/', $dayMonth);
  if (count($parts) !== 2) return null;

  $d = (int)$parts[0];
  $m = (int)$parts[1];

  return DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $year, $m, $d));
}

/**
 * Descobre o signo a partir de uma data (DateTime) e do XML carregado
 * - Suporta intervalo que "vira o ano" (Capricórnio, Aquário, etc)
 */
function findZodiacSign($birthDate, $signosXml) {
  $year = (int)$birthDate->format('Y');

  foreach ($signosXml->signo as $signo) {
    $inicio = brDayMonthToDate((string)$signo->dataInicio, $year);
    $fim    = brDayMonthToDate((string)$signo->dataFim, $year);

    if (!$inicio || !$fim) continue;

    // caso normal (ex: 21/03 - 20/04)
    if ($inicio <= $fim) {
      if ($birthDate >= $inicio && $birthDate <= $fim) return $signo;
    } else {
      // caso que vira o ano (ex: 22/12 - 19/01)
      $endOfYear   = DateTime::createFromFormat('Y-m-d', $year . '-12-31');
      $startOfYear = DateTime::createFromFormat('Y-m-d', $year . '-01-01');

      if (($birthDate >= $inicio && $birthDate <= $endOfYear) ||
          ($birthDate >= $startOfYear && $birthDate <= $fim)) {
        return $signo;
      }
    }
  }

  return null;
}

/**
 * Mapeia nome PT -> slug EN da API (aztro)
 */
function mapSignToAztroSlug($nomePt) {
  $map = [
    'Áries' => 'aries',
    'Aries' => 'aries',
    'Touro' => 'taurus',
    'Gêmeos' => 'gemini',
    'Gemeos' => 'gemini',
    'Câncer' => 'cancer',
    'Cancer' => 'cancer',
    'Leão' => 'leo',
    'Leao' => 'leo',
    'Virgem' => 'virgo',
    'Libra' => 'libra',
    'Escorpião' => 'scorpio',
    'Escorpiao' => 'scorpio',
    'Sagitário' => 'sagittarius',
    'Sagitario' => 'sagittarius',
    'Capricórnio' => 'capricorn',
    'Capricornio' => 'capricorn',
    'Aquário' => 'aquarius',
    'Aquario' => 'aquarius',
    'Peixes' => 'pisces',
  ];

  return $map[$nomePt] ?? null;
}

/**
 * Chama a API Aztro e retorna array com dados ou null
 */
function fetchDailyHoroscopeAztro($signSlug, $day = 'today') {
  $url = 'https://aztro.sameerkumar.website/?sign=' . urlencode($signSlug) . '&day=' . urlencode($day);

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 8,
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($response === false || $httpCode < 200 || $httpCode >= 300) {
    return null;
  }

  $json = json_decode($response, true);
  if (!is_array($json)) return null;

  return $json;
}

// ----------------------------
// Entrada do formulário
// ----------------------------
$data_nascimento = $_POST['data_nascimento'] ?? null;

// Layout principal (igual ao print)
echo "<div class='z-wrap'><div>";

// Header “bonito”
echo "
  <div class='z-hero'>
    <div class='z-title'>🌙 Descubra seu Signo ✨</div>
    <div class='z-subtitle'>Insira sua data de nascimento e descubra os mistérios do seu signo zodiacal</div>
  </div>
";

if (!$data_nascimento) {
  echo "
    <div class='z-card'>
      <div class='z-card-inner'>
        <div class='alert alert-danger mb-0'>Data de nascimento não enviada.</div>
        <div class='mt-3 text-center'>
          <a class='z-link' href='index.php'>Voltar</a>
        </div>
      </div>
    </div>
  ";
  echo "</div></div></body></html>";
  exit;
}

$birthDate = DateTime::createFromFormat('Y-m-d', $data_nascimento);
if (!$birthDate) {
  echo "
    <div class='z-card'>
      <div class='z-card-inner'>
        <div class='alert alert-danger mb-0'>Data inválida.</div>
        <div class='mt-3 text-center'>
          <a class='z-link' href='index.php'>Voltar</a>
        </div>
      </div>
    </div>
  ";
  echo "</div></div></body></html>";
  exit;
}

// Carrega XML
$signos = simplexml_load_file('signos.xml');
if (!$signos) {
  echo "
    <div class='z-card'>
      <div class='z-card-inner'>
        <div class='alert alert-danger mb-0'>Não foi possível ler o arquivo <strong>signos.xml</strong>.</div>
        <div class='mt-3 text-center'>
          <a class='z-link' href='index.php'>Voltar</a>
        </div>
      </div>
    </div>
  ";
  echo "</div></div></body></html>";
  exit;
}

$signoEncontrado = findZodiacSign($birthDate, $signos);
if (!$signoEncontrado) {
  echo "
    <div class='z-card'>
      <div class='z-card-inner'>
        <div class='alert alert-warning mb-0'>Não encontrei seu signo. Confira as datas no XML.</div>
        <div class='mt-3 text-center'>
          <a class='z-link' href='index.php'>Tentar outra data</a>
        </div>
      </div>
    </div>
  ";
  echo "</div></div></body></html>";
  exit;
}

// Dados do XML (com fallbacks)
$nome = (string)$signoEncontrado->signoNome;
$descricao = (string)$signoEncontrado->descricao;
$simbolo = (string)($signoEncontrado->simbolo ?? '');
$cor = (string)($signoEncontrado->cor ?? '');        // opcional
$imagem = (string)($signoEncontrado->imagem ?? '');  // opcional

// Horóscopo do dia via Aztro
$slug = mapSignToAztroSlug($nome);
$horoscope = null;
if ($slug) {
  $horoscope = fetchDailyHoroscopeAztro($slug, 'today');
}

// --- Card resultado ---
$badgeStyle = $cor ? "style='background: {$cor};'" : "";
// Se você usar a versão do CSS que deixa a badge dourada, pode ignorar $cor.
// Se quiser a badge colorida por signo, a gente ajusta o CSS depois.

echo "
  <div class='z-card'>
    <div class='z-card-inner'>
      <div class='z-badge'>
        <div class='z-symbol'>" . e($simbolo) . "</div>
      </div>

      <div style='text-align:center; color: rgba(233,233,243,.6); letter-spacing:.18em; font-size:11px;'>
        ✨ SEU SIGNO É ✨
      </div>

      <h1 class='z-sign'>" . e($nome) . "</h1>
      <p class='z-desc'>" . e($descricao) . "</p>
";

// Imagem (se existir no XML)
if ($imagem) {
  echo "
    <div class='text-center mb-3'>
      <img src='" . e($imagem) . "' alt='Imagem do signo " . e($nome) . "' style='width: 90px; height: 90px; object-fit: contain;' />
    </div>
  ";
}

// Horóscopo do dia (se a API respondeu)
if ($horoscope && isset($horoscope['description'])) {
  $todayDate = $horoscope['current_date'] ?? '';
  $mood = $horoscope['mood'] ?? '';
  $lucky = $horoscope['lucky_number'] ?? '';
  $compat = $horoscope['compatibility'] ?? '';
  $color = $horoscope['color'] ?? '';

  echo "
    <div style='margin-top: 18px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,.08);'>
      <div style='text-align:center; color: rgba(233,233,243,.7); font-weight: 700;'>
        Horóscopo do dia " . ($todayDate ? "• " . e($todayDate) : "") . "
      </div>

      <p class='z-desc' style='margin-top: 10px; margin-bottom: 0;'>" . e($horoscope['description']) . "</p>

      <div class='mt-3' style='display:flex; gap:10px; flex-wrap:wrap; justify-content:center; color: rgba(233,233,243,.70); font-size: 13px;'>
        " . ($mood ? "<div>✨ <strong>Humor:</strong> " . e($mood) . "</div>" : "") . "
        " . ($color ? "<div>🎨 <strong>Cor:</strong> " . e($color) . "</div>" : "") . "
        " . ($lucky ? "<div>🍀 <strong>Nº da sorte:</strong> " . e($lucky) . "</div>" : "") . "
        " . ($compat ? "<div>🤝 <strong>Compatibilidade:</strong> " . e($compat) . "</div>" : "") . "
      </div>
    </div>
  ";
} else {
  echo "
    <div class='mt-3' style='text-align:center; color: rgba(233,233,243,.55); font-size: 13px;'>
      (Não consegui carregar o horóscopo do dia agora. Tente novamente mais tarde.)
    </div>
  ";
}

echo "
      <div style='text-align:center; margin-top: 18px;'>
        <a class='z-link' href='index.php'>Tentar outra data</a>
      </div>

    </div>
  </div>
";

echo "</div></div></body></html>";
