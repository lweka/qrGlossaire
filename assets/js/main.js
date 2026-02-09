document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".faq-item button").forEach((button) => {
    button.addEventListener("click", () => {
      const item = button.closest(".faq-item");
      item.classList.toggle("active");
    });
  });

  const statCounters = document.querySelectorAll("[data-count]");
  statCounters.forEach((counter) => {
    const target = Number(counter.dataset.count || 0);
    let current = 0;
    const step = Math.max(1, Math.floor(target / 60));

    const tick = () => {
      current += step;
      if (current >= target) {
        counter.textContent = target;
        return;
      }
      counter.textContent = current;
      requestAnimationFrame(tick);
    };

    requestAnimationFrame(tick);
  });

  const eventType = document.getElementById("event_type");
  const activityGroup = document.getElementById("activity_group");
  if (eventType && activityGroup) {
    const toggleActivity = () => {
      const isOther = eventType.value === "other";
      activityGroup.style.display = isOther ? "flex" : "none";
    };
    eventType.addEventListener("change", toggleActivity);
    toggleActivity();
  }

  const slider = document.querySelector("[data-slider]");
  if (slider) {
    const slides = Array.from(slider.querySelectorAll(".slide"));
    const dotsContainer = slider.querySelector("[data-dots]");
    let currentIndex = 0;

    const goToSlide = (index) => {
      slides[currentIndex].classList.remove("active");
      if (dotsContainer) {
        dotsContainer.children[currentIndex]?.classList.remove("active");
      }
      currentIndex = index;
      slides[currentIndex].classList.add("active");
      if (dotsContainer) {
        dotsContainer.children[currentIndex]?.classList.add("active");
      }
    };

    if (dotsContainer) {
      dotsContainer.innerHTML = "";
      slides.forEach((_, index) => {
        const dot = document.createElement("button");
        if (index === 0) dot.classList.add("active");
        dot.addEventListener("click", () => goToSlide(index));
        dotsContainer.appendChild(dot);
      });
    }

    setInterval(() => {
      const nextIndex = (currentIndex + 1) % slides.length;
      goToSlide(nextIndex);
    }, 4500);
  }
});
