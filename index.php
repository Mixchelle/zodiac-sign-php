<?php include('layouts/header.php'); ?>

<div class="z-wrap">
  <div>
    <div class="z-hero">
      <div class="z-title">🌙 Descubra seu Signo ✨</div>
      <div class="z-subtitle">
        Insira sua data de nascimento e descubra os mistérios do seu signo zodiacal
      </div>
    </div>

    <div class="z-card">
      <div class="z-card-inner">
        <div class="z-label">Qual é a sua data de nascimento?</div>

        <form method="POST" action="show_zodiac_sign.php">
          <div class="mb-3">
            <input class="z-input" type="date" name="data_nascimento" required />
          </div>

          <button class="z-btn" type="submit">⭐ Descobrir meu signo</button>
        </form>
      </div>
    </div>
  </div>
</div>

</body>
</html>
