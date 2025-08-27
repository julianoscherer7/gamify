// js/app-advanced.js
// Frontend controller for advanced dashboard
document.addEventListener('DOMContentLoaded', () => {
    initDashboard();
  });
  
  async function initDashboard() {
    await loadUser();
    await populateGenresAndBooks();
    await loadLeaderboard();
    setupUIHandlers();
  }
  
  async function loadUser(){
    try{
      const res = await fetch('backend/user_points.php');
      const j = await res.json();
      if(j.ok){
        const u = j.user;
        document.getElementById('ui-username').textContent = u.username;
        document.getElementById('ui-points').textContent = u.points;
        document.getElementById('ui-total-books').textContent = u.total_books;
        document.getElementById('ui-streak').textContent = u.streak;
        document.getElementById('ui-badge').textContent = u.badge;
        // next rank
        if (j.next_rank && j.next_rank.min !== undefined) {
          const nextName = j.next_rank.code;
          const needed = Math.max(0, (j.next_rank.min || 0) - u.points);
          document.getElementById('ui-next-rank').textContent = `${nextName} em ${needed} pontos`;
          const progressPercent = computeProgressPercent(u.points, j.next_rank.min);
          document.getElementById('progress').style.width = progressPercent + '%';
        }
        // achievements preview
        renderAchievements(j.achievements || []);
      } else {
        window.location.href = 'login.html';
      }
    } catch (err){
      console.error(err);
    }
  }
  
  function computeProgressPercent(points, nextThreshold) {
    if(!nextThreshold || nextThreshold <= 0) return 100;
    // basic mapping: if nextThreshold is high, compute proportion up to threshold
    const prevThresholds = [0,100,500,2000,10000];
    let prev = 0;
    for(let i=0;i<prevThresholds.length;i++){
      if (nextThreshold === prevThresholds[i]) {
        prev = prevThresholds[Math.max(0,i-1)];
        break;
      }
    }
    const part = (points - prev) / Math.max(1, (nextThreshold - prev));
    return Math.round(Math.max(0, Math.min(1, part))*100);
  }
  
  async function populateGenresAndBooks(){
    try{
      const res = await fetch('backend/books.php');
      const books = await res.json();
      // compute unique genres
      const genres = new Set();
      books.forEach(b => genres.add(b.genre || 'General'));
      const genreSelect = document.getElementById('filter-genre');
      if (genreSelect) {
        genreSelect.innerHTML = '<option value="">Todos</option>';
        Array.from(genres).sort().forEach(g=>{
          const opt = document.createElement('option'); opt.value = g; opt.textContent = g; genreSelect.appendChild(opt);
        });
        genreSelect.addEventListener('change', () => renderBooks(books));
      }
      window.__books_cache = books;
      renderBooks(books);
    } catch (err) { console.error(err); }
  }
  
  function renderBooks(books) {
    const filter = document.getElementById('filter-genre');
    const genre = filter ? filter.value : '';
    const list = document.getElementById('book-list');
    list.innerHTML = '';
    books.filter(b => !genre || b.genre === genre).forEach(b => {
      const li = document.createElement('li');
      li.className = 'book-item';
      li.innerHTML = `
        <div>
          <div class="book-title">${escapeHtml(b.title)}</div>
          <div class="book-meta">${escapeHtml(b.genre)} • ${escapeHtml(b.description||'')} • ${b.points} pts</div>
        </div>
        <div class="actions"></div>
      `;
      const actions = li.querySelector('.actions');
      if (b.completed) {
        const done = document.createElement('button'); done.className='btn light'; done.textContent='Lido ✓';
        actions.appendChild(done);
        const info = document.createElement('div'); info.className='small muted'; info.textContent = `Lido em ${b.completed_at || '—'}`; actions.appendChild(info);
      } else {
        const btn = document.createElement('button'); btn.className='btn'; btn.textContent='Marcar como lido';
        btn.addEventListener('click', ()=> markAsRead(b.id, btn));
        actions.appendChild(btn);
      }
      list.appendChild(li);
    });
  }
  
  async function markAsRead(bookId, btn){
    btn.disabled = true; const orig = btn.textContent; btn.textContent='Salvando...';
    try{
      const res = await fetch('backend/add_book.php', {method:'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({book_id: bookId})});
      const j = await res.json();
      if (j.ok) {
        toast(`+${j.points_added} pontos! Streak: ${j.new_streak} dias.`);
        await loadUser();
        // refresh book cache and rerender
        const bres = await fetch('backend/books.php'); const books = await bres.json(); window.__books_cache = books;
        renderBooks(books);
        await loadLeaderboard();
        // if achievements present, show small modal
        if (j.achievements && j.achievements.length>0) {
          showAchievementsModal(j.achievements);
        }
      } else {
        toast('Erro: ' + (j.error || 'não foi possível'));
        btn.disabled = false; btn.textContent = orig;
      }
    } catch (err){
      console.error(err); toast('Erro de rede'); btn.disabled = false; btn.textContent = orig;
    }
  }
  
  async function loadLeaderboard(){
    try{
      const mode = document.getElementById('leader-mode') ? document.getElementById('leader-mode').value : 'overall';
      let url = `backend/leaderboard.php?mode=${encodeURIComponent(mode)}&limit=8`;
      if (mode === 'genre') {
        const g = document.getElementById('filter-genre') ? document.getElementById('filter-genre').value : '';
        if (g) url += '&genre=' + encodeURIComponent(g);
      }
      const res = await fetch(url);
      const j = await res.json();
      if (j.ok) {
        const ol = document.getElementById('leaderboard');
        ol.innerHTML = '';
        j.leaderboard.forEach((u, idx) => {
          const li = document.createElement('li');
          li.textContent = `${idx+1}. ${u.username} — ${u.points} pts`;
          ol.appendChild(li);
        });
      }
    } catch (err) { console.error(err); }
  }
  
  function setupUIHandlers(){
    const leaderMode = document.getElementById('leader-mode');
    if (leaderMode) {
      leaderMode.addEventListener('change', loadLeaderboard);
    }
    const filterGenre = document.getElementById('filter-genre');
    if (filterGenre) {
      filterGenre.addEventListener('change', async ()=>{
        await loadLeaderboard();
        renderBooks(window.__books_cache || []);
      });
    }
    const showAchBtn = document.getElementById('show-achievements');
    if (showAchBtn) showAchBtn.addEventListener('click', async ()=>{
      const res = await fetch('backend/achievements.php');
      const j = await res.json();
      if (j.ok) {
        openAchievementsModal(j.achievements, j.unlocked || {});
      }
    });
    const closeModal = document.getElementById('close-ach-modal');
    if (closeModal) closeModal.addEventListener('click', ()=> closeAchievementsModal());
  }
  
  function renderAchievements(achievements) {
    const container = document.getElementById('achievements-list');
    container.innerHTML = '';
    achievements.slice(0,4).forEach(a=>{
      const d = document.createElement('div'); d.className='ach-card';
      d.innerHTML = `${a.icon || '🏆'} <div><strong>${escapeHtml(a.title)}</strong><div class="small muted">${escapeHtml(a.description)}</div></div>`;
      container.appendChild(d);
    });
  }
  
  // achievements modal functions
  function openAchievementsModal(all, unlockedMap) {
    const modal = document.getElementById('ach-modal'); const box = document.getElementById('modal-achievements');
    modal.className = ''; box.innerHTML = '';
    all.forEach(a=>{
      const el = document.createElement('div'); el.className='ach-card';
      const unlocked = unlockedMap && unlockedMap[a.code];
      el.innerHTML = `<div style="font-size:20px">${a.icon||'🏆'}</div><div style="margin-left:8px"><strong>${a.title}</strong><div class="small muted">${a.description}</div>${unlocked?'<div class="small">Desbloqueado: '+unlocked+'</div>':''}</div>`;
      box.appendChild(el);
    });
  }
  
  function showAchievementsModal(achList) {
    // quick popup when new achievements unlocked
    let html = achList.map(a => `<div class="ach-card">${a.icon||'🏆'} <div style="margin-left:8px"><strong>${escapeHtml(a.title)}</strong><div class="small muted">${escapeHtml(a.description||'')}</div></div></div>`).join('');
    const modal = document.getElementById('ach-modal');
    modal.className = '';
    document.getElementById('modal-achievements').innerHTML = html;
    setTimeout(()=> closeAchievementsModal(), 5000);
  }
  
  function closeAchievementsModal(){
    const modal = document.getElementById('ach-modal'); modal.className = 'modal-hidden';
  }
  
  function toast(msg){
    const d = document.createElement('div');
    d.className = 'toast';
    d.textContent = msg;
    Object.assign(d.style, {position:'fixed',right:'20px',bottom:'20px',background:'linear-gradient(90deg,#ffb703,#fca311)',color:'#071226',padding:'10px 14px',borderRadius:'10px',zIndex:9999});
    document.body.appendChild(d);
    setTimeout(()=> d.remove(), 3000);
  }
  
  function escapeHtml(s){ if(!s) return ''; return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
  