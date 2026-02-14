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

  const textSlider = document.querySelector("[data-text-slider]");
  if (textSlider) {
    const textTarget = textSlider.querySelector("[data-text-current]");
    const rawPhrases = textSlider.dataset.textPhrases || "";
    const phrases = rawPhrases
      .split("||")
      .map((phrase) => phrase.trim())
      .filter(Boolean);

    if (textTarget && phrases.length > 1) {
      let phraseIndex = 0;

      const switchPhrase = () => {
        textTarget.classList.add("is-hiding");

        window.setTimeout(() => {
          phraseIndex = (phraseIndex + 1) % phrases.length;
          textTarget.textContent = phrases[phraseIndex];
          textTarget.classList.remove("is-hiding");
        }, 320);
      };

      window.setInterval(switchPhrase, 3600);
    }
  }

  const slider = document.querySelector("[data-slider]");
  if (slider) {
    const slides = Array.from(slider.querySelectorAll(".slide"));
    const dotsContainer = slider.querySelector("[data-dots]");
    let currentIndex = 0;

    if (slides.length === 0) {
      if (dotsContainer) {
        dotsContainer.innerHTML = "";
      }
      return;
    }

    slides.forEach((slide, index) => {
      slide.classList.toggle("active", index === 0);
    });

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
      if (slides.length > 1) {
        slides.forEach((_, index) => {
          const dot = document.createElement("button");
          if (index === 0) dot.classList.add("active");
          dot.addEventListener("click", () => goToSlide(index));
          dotsContainer.appendChild(dot);
        });
      }
    }

    if (slides.length > 1) {
      setInterval(() => {
        const nextIndex = (currentIndex + 1) % slides.length;
        goToSlide(nextIndex);
      }, 4500);
    }
  }
});
