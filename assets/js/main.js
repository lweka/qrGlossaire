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
      const transitionDuration = 320;
      const intervalDuration = 3200;

      const switchPhrase = () => {
        textTarget.classList.add("is-hiding");

        window.setTimeout(() => {
          phraseIndex = (phraseIndex + 1) % phrases.length;
          textTarget.textContent = phrases[phraseIndex];
          textTarget.classList.remove("is-hiding");
        }, transitionDuration);
      };

      window.setTimeout(switchPhrase, 1200);
      window.setInterval(switchPhrase, intervalDuration);
    }
  }

  const invitationShowcases = Array.from(
    document.querySelectorAll("[data-invitation-showcase]")
  );

  const syncInvitationShowcase = (showcase, slide) => {
    const headline = showcase.querySelector("[data-invitation-current-headline]");
    const meta = showcase.querySelector("[data-invitation-current-meta]");
    const date = showcase.querySelector("[data-invitation-current-date]");
    const location = showcase.querySelector("[data-invitation-current-location]");
    const dressCode = showcase.querySelector(
      "[data-invitation-current-dress-code]"
    );
    const rsvpDeadline = showcase.querySelector(
      "[data-invitation-current-rsvp-deadline]"
    );

    const dataset = slide?.dataset || {};
    const safeValue = (value, fallback) =>
      value && String(value).trim() !== "" ? value : fallback;

    if (headline) {
      headline.textContent = safeValue(
        dataset.invitationHeadline,
        "Invitation Personnalisee"
      );
    }
    if (meta) {
      meta.textContent = safeValue(
        dataset.invitationMeta,
        "Configurez votre modele et partagez-le en QR Code."
      );
    }
    if (date) {
      date.textContent = safeValue(dataset.invitationDate, "A definir");
    }
    if (location) {
      location.textContent = safeValue(dataset.invitationLocation, "A definir");
    }
    if (dressCode) {
      dressCode.textContent = safeValue(
        dataset.invitationDressCode,
        "A definir"
      );
    }
    if (rsvpDeadline) {
      rsvpDeadline.textContent = safeValue(
        dataset.invitationRsvpDeadline,
        "A definir"
      );
    }
  };

  invitationShowcases.forEach((showcase) => {
    const slider = showcase.querySelector("[data-slider]");
    if (!slider) {
      return;
    }

    slider.addEventListener("slider:change", (event) => {
      syncInvitationShowcase(showcase, event.detail?.slide || null);
    });

    const initialSlide =
      slider.querySelector(".slide.active") || slider.querySelector(".slide");
    syncInvitationShowcase(showcase, initialSlide);

    const rsvpButton = showcase.querySelector("[data-rsvp-button]");
    const rsvpStatus = showcase.querySelector("[data-rsvp-status]");
    if (rsvpButton && rsvpStatus) {
      rsvpButton.addEventListener("click", () => {
        const activeSlide =
          slider.querySelector(".slide.active") || slider.querySelector(".slide");
        const invitationName =
          activeSlide?.dataset?.invitationHeadline || "cette invitation";
        const deadline =
          activeSlide?.dataset?.invitationRsvpDeadline || "la date prevue";
        rsvpStatus.textContent = `Presence confirmee pour ${invitationName}. Reponse enregistree avant ${deadline}.`;
        rsvpStatus.classList.add("is-success");
      });
    }
  });

  const initializeSlider = (slider) => {
    const slides = Array.from(slider.querySelectorAll(".slide"));
    const dotsContainer = slider.querySelector("[data-dots]");
    let currentIndex = 0;

    const emitSlideChange = () => {
      slider.dispatchEvent(
        new CustomEvent("slider:change", {
          detail: {
            index: currentIndex,
            slide: slides[currentIndex] || null,
          },
        })
      );
    };

    if (slides.length === 0) {
      if (dotsContainer) {
        dotsContainer.innerHTML = "";
      }
      emitSlideChange();
      return;
    }

    const goToSlide = (index) => {
      if (!slides[index] || index === currentIndex) {
        return;
      }

      slides[currentIndex].classList.remove("active");
      if (dotsContainer) {
        dotsContainer.children[currentIndex]?.classList.remove("active");
      }

      currentIndex = index;
      slides[currentIndex].classList.add("active");
      if (dotsContainer) {
        dotsContainer.children[currentIndex]?.classList.add("active");
      }

      emitSlideChange();
    };

    slides.forEach((slide, index) => {
      slide.classList.toggle("active", index === 0);
    });

    if (dotsContainer) {
      dotsContainer.innerHTML = "";
      if (slides.length > 1) {
        slides.forEach((_, index) => {
          const dot = document.createElement("button");
          dot.type = "button";
          if (index === 0) dot.classList.add("active");
          dot.addEventListener("click", () => goToSlide(index));
          dotsContainer.appendChild(dot);
        });
      }
    }

    emitSlideChange();

    if (slides.length > 1) {
      window.setInterval(() => {
        const nextIndex = (currentIndex + 1) % slides.length;
        goToSlide(nextIndex);
      }, 4500);
    }
  };

  document.querySelectorAll("[data-slider]").forEach((slider) => {
    initializeSlider(slider);
  });
});
