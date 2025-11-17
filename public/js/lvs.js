const MIN_CHARS = 2;
const DEBOUNCE_MS = 250;

let STUDENTS = LEERLINGEN_DATA.slice();
const qInput = document.getElementById('q');
const studentGrid = document.getElementById('studentGrid');
const infoCard = document.getElementById('infoCard');

// ===== Uitloggen =====
document.getElementById('logoutBtn').addEventListener('click', ()=>{
    localStorage.clear();
    window.location.href = '/logout';
});

// ===== Helpers =====
const debounce = (fn,ms)=>{ let t; return (...args)=>{ clearTimeout(t); t=setTimeout(()=>fn(...args),ms); }; };
function normalize(s){ return (s||'').toString().trim(); }
function fullName(s){
    const achternaam = s.achternaam ?? s.naam ?? '';
    return `${(s.voornaam||'').trim()} ${achternaam.trim()}`.trim();
}
// "Klas-achtige" input detecteren: cijfer(s) + minstens 1 letter (bv. "3 e", "3 EL", "2 STEM")
function isClassQuery(q){
    return /^\s*\d{1,2}\s*[A-Za-zÀ-ÿ]/i.test(q);
}

// Alles netjes lowercased en spaties samenvouwen voor vergelijkingen
function normalizeClass(s){
    return (s||'').toString().toLowerCase().replace(/\s+/g,' ').trim();
}

// ===== InfoCard helpers =====
function showInfo(html) {
    if (!infoCard) return;
    infoCard.innerHTML = html;
    infoCard.removeAttribute('data-hidden');
}
function hideInfo() {
    if (!infoCard) return;
    infoCard.setAttribute('data-hidden', 'true');
    infoCard.innerHTML = '';
}

// ===== Filter + Render =====
function filterStudents(q){
    const raw = normalize(q);
    const term = raw.toLowerCase();

    // Klas-achtige query: prefix match, niet exact
    if (isClassQuery(raw)) {
        const classQ = normalizeClass(raw);        // bv. "3 e"
        return STUDENTS.filter(s => normalizeClass(s.klas).startsWith(classQ));
    }

    // Naam/algemene query: bevat-match op naam of klas
    if (term.length < MIN_CHARS) return [];
    return STUDENTS.filter(s => {
        const nm = fullName(s).toLowerCase();
        const kl = (s.klas||'').toLowerCase();
        return nm.includes(term) || kl.includes(term);
    });
}

function render(list){
    studentGrid.innerHTML = '';

    const q = normalize(qInput.value);
    const isEmpty = q.length === 0;
    const valid = isClassQuery(q) || q.length >= MIN_CHARS;

    // 1) Leeg zoekveld -> infoCard verbergen, grid leeg
    if (isEmpty) {
        hideInfo();
        return;
    }

    // 2) Niet leeg maar ongeldige/te korte zoekterm -> infoCard verbergen, grid leeg
    if (!valid) {
        hideInfo();
        return;
    }

    // 3) Geldige zoekterm:
    if (!list.length){
        // Alleen hier tonen we de infoCard (geen tip meer)
        const safeTerm = q.replace(/[<>&]/g, s => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[s]));
        showInfo(`Geen resultaten voor <b>${safeTerm}</b>.`);
        return;
    }

    // 4) Matches -> infoCard verbergen en knoppen renderen
    hideInfo();
    list.forEach((s,i)=>{
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = `btn ${i%2===0 ? 'btn-green':'btn-orange'}`;
        btn.innerHTML = `<span>${fullName(s)}</span><span class="tag">${s.klas}</span>`;
        btn.addEventListener('click', ()=> chooseStudent(s));
        studentGrid.appendChild(btn);
    });
}

// ===== Actie bij keuze =====
function chooseStudent(s){
    try{ localStorage.setItem('speelhof.selectedStudent', JSON.stringify(s)); }catch(e){}
    window.location.href = `/leerlingen/${s.id}`;
}

// ===== INIT =====
function init(){
    hideInfo(); // standaard verborgen bij load

    const onQChange = debounce(()=>{
        const q = normalize(qInput.value);
        render(filterStudents(q));
    }, DEBOUNCE_MS);

    qInput.addEventListener('input', onQChange);
    qInput.addEventListener('keydown', e=>{ if(e.key==='Enter') onQChange(); });
}
init();
