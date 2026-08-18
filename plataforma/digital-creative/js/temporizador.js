// Temporizador para el Reto Arjuna - Versión mejorada
document.addEventListener('DOMContentLoaded', function() {
    // Fecha del reto: 27 de marzo 2026, 6:00 AM CDMX
    const retoDate = new Date(2026, 2, 27, 6, 0, 0).getTime();
    
    const floatingTimer = document.getElementById('floatingTimer');
    const timerContainer = document.getElementById('timerContainer');
    const daysEl = document.getElementById('days');
    const hoursEl = document.getElementById('hours');
    const minutesEl = document.getElementById('minutes');
    const secondsEl = document.getElementById('seconds');
    const timerLabel = document.querySelector('.timer-label');
    
    // Verificar si hay un estado guardado
    const isMinimized = localStorage.getItem('timerMinimized') === 'true';
    if (isMinimized) {
        timerContainer.classList.add('minimized');
        document.getElementById('toggleTimer').textContent = '+';
    }
    
    function updateCountdown() {
        const now = new Date().getTime();
        const distance = retoDate - now;
        
        if (distance < 0) {
            // El reto ya comenzó o pasó
            daysEl.textContent = '00';
            hoursEl.textContent = '00';
            minutesEl.textContent = '00';
            secondsEl.textContent = '00';
            
            if (timerLabel) {
                timerLabel.textContent = 'RETO EN CURSO';
            }
            return;
        }
        
        // Cálculos de tiempo
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
        // Actualizar DOM con formato de dos dígitos
        daysEl.textContent = days < 10 ? '0' + days : days;
        hoursEl.textContent = hours < 10 ? '0' + hours : hours;
        minutesEl.textContent = minutes < 10 ? '0' + minutes : minutes;
        secondsEl.textContent = seconds < 10 ? '0' + seconds : seconds;
    }
    
    // Actualizar cada segundo
    updateCountdown();
    setInterval(updateCountdown, 1000);
    
    // Mostrar el temporizador con animación
    setTimeout(() => {
        floatingTimer.style.opacity = '1';
    }, 1500);
});

// Función para minimizar/expandir el temporizador
function toggleTimer() {
    const timerContainer = document.getElementById('timerContainer');
    const toggleBtn = document.getElementById('toggleTimer');
    
    timerContainer.classList.toggle('minimized');
    
    // Cambiar el símbolo del botón
    if (timerContainer.classList.contains('minimized')) {
        toggleBtn.textContent = '+';
        localStorage.setItem('timerMinimized', 'true');
    } else {
        toggleBtn.textContent = '−';
        localStorage.setItem('timerMinimized', 'false');
    }
}