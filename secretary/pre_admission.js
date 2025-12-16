document.addEventListener("DOMContentLoaded", function() {
  console.log("Initialisation du formulaire de pré-admission...");
  
  // Éléments principaux
  const steps = document.querySelectorAll(".form-step");
  const stepIndicators = document.querySelectorAll(".step");
  const nextBtn = document.getElementById("nextBtn");
  const prevBtn = document.getElementById("prevBtn");
  const stepIndicator = document.getElementById("stepIndicator");
  const progressBar = document.getElementById("progress-bar");
  
  // Vérification des éléments critiques
  if (steps.length === 0) {
      console.error("Aucun élément .form-step trouvé");
      return;
  }
  
  let currentStep = 0;
  
  // Fonction pour afficher une étape
  function showStep(n) {
      console.log(`Affichage de l'étape ${n + 1}/${steps.length}`);
      
      // Masquer toutes les étapes
      steps.forEach((step, i) => {
          step.style.display = i === n ? "block" : "none";
      });
      
      // Mettre à jour les indicateurs
      stepIndicators.forEach((indicator, index) => {
          indicator.classList.remove("active", "completed");
          if (index < n) {
              indicator.classList.add("completed");
          } else if (index === n) {
              indicator.classList.add("active");
          }
      });
      
      // Mettre à jour les boutons
      if (prevBtn) {
          prevBtn.disabled = n === 0;
          prevBtn.style.display = n === 0 ? "none" : "inline-block";
      }
      
      if (nextBtn) {
          nextBtn.style.display = n === steps.length - 1 ? "none" : "inline-block";
      }
      
      // Mettre à jour l'indicateur textuel
      if (stepIndicator) {
          stepIndicator.textContent = `Étape ${n + 1} sur ${steps.length}`;
      }
      
      // Mettre à jour la barre de progression
      if (progressBar) {
          const progress = ((n + 1) / steps.length) * 100;
          progressBar.style.width = `${progress}%`;
          progressBar.textContent = `${Math.round(progress)}%`;
      }
      
      // ===== CORRECTION IMPORTANTE : Afficher le livret de famille si nécessaire =====
      if (n === 2) { // Étape Documents (index 2)
          console.log("Nous sommes à l'étape Documents");
          
          // Vérifier l'état de la checkbox "Patient mineur"
          const minorCheckbox = document.getElementById("is_minor");
          console.log("Checkbox 'is_minor' trouvée:", minorCheckbox !== null);
          
          if (minorCheckbox) {
              console.log("État de la checkbox:", minorCheckbox.checked);
              
              // Essayer différents IDs possibles
              const minorFieldsIds = ["minor_fields", "minor_document", "livret_famille_container"];
              let minorFieldsDiv = null;
              
              for (const id of minorFieldsIds) {
                  minorFieldsDiv = document.getElementById(id);
                  if (minorFieldsDiv) {
                      console.log(`Div trouvée avec l'ID: ${id}`);
                      break;
                  }
              }
              
              if (minorFieldsDiv) {
                  console.log("Style display avant:", minorFieldsDiv.style.display);
                  minorFieldsDiv.style.display = minorCheckbox.checked ? "block" : "none";
                  console.log("Style display après:", minorFieldsDiv.style.display);
                  
                  // Rendre le champ obligatoire si le patient est mineur
                  const livretFamilleInput = document.querySelector('input[name="livret_famille"]');
                  if (livretFamilleInput) {
                      if (minorCheckbox.checked) {
                          livretFamilleInput.setAttribute("required", "required");
                          console.log("livret_famille marqué comme required");
                      } else {
                          livretFamilleInput.removeAttribute("required");
                          console.log("livret_famille n'est plus required");
                      }
                  }
              } else {
                  console.log("Aucune div minor_fields trouvée. Recherche par classe...");
                  
                  // Chercher par classe ou par nom
                  const possibleDivs = document.querySelectorAll('[class*="minor"], [class*="livret"]');
                  possibleDivs.forEach(div => {
                      console.log("Div trouvée:", div.className);
                      if (minorCheckbox.checked) {
                          div.style.display = "block";
                      } else {
                          div.style.display = "none";
                      }
                  });
              }
          }
      }
  }
  
  // Navigation
  if (nextBtn) {
      nextBtn.addEventListener("click", () => {
          if (validateStep(currentStep)) {
              currentStep++;
              if (currentStep >= steps.length) currentStep = steps.length - 1;
              showStep(currentStep);
          }
      });
  }
  
  if (prevBtn) {
      prevBtn.addEventListener("click", () => {
          currentStep--;
          if (currentStep < 0) currentStep = 0;
          showStep(currentStep);
      });
  }
  
  // ===== TOGGLE NOUVEAU PATIENT =====
  const newPatientRadios = document.querySelectorAll('input[name="new_patient"]');
  
  if (newPatientRadios.length > 0) {
      console.log("Boutons radio 'new_patient' trouvés:", newPatientRadios.length);
      
      newPatientRadios.forEach(radio => {
          radio.addEventListener("change", function(e) {
              console.log("Type de patient sélectionné:", e.target.value);
              const isNewPatient = e.target.value === '1';
              
              // Récupérer les éléments avec leurs IDs exacts
              const newPatientFields = document.getElementById("new_patient_fields");
              const existingPatientDiv = document.getElementById("existing_patient");
              
              console.log("new_patient_fields trouvé:", newPatientFields !== null);
              console.log("existing_patient trouvé:", existingPatientDiv !== null);
              
              // Basculer l'affichage
              if (newPatientFields) {
                  newPatientFields.style.display = isNewPatient ? "block" : "none";
              }
              
              if (existingPatientDiv) {
                  existingPatientDiv.style.display = isNewPatient ? "none" : "block";
              }
              
              // Réinitialiser les erreurs
              document.querySelectorAll('.error-message').forEach(error => {
                  error.style.display = 'none';
              });
              document.querySelectorAll('.field-error').forEach(field => {
                  field.classList.remove('field-error');
              });
          });
      });
      
      // Initialiser l'état au chargement
      const initialSelection = document.querySelector('input[name="new_patient"]:checked');
      if (initialSelection) {
          initialSelection.checked = true;
          const event = new Event('change');
          initialSelection.dispatchEvent(event);
      }
  } else {
      console.warn("Aucun bouton radio 'new_patient' trouvé");
  }
  
  // ===== GESTION DU PATIENT MINEUR =====
  const minorCheckbox = document.getElementById("is_minor");
  
  if (minorCheckbox) {
      console.log("Checkbox 'is_minor' trouvée");
      
      // Mettre à jour l'état au changement
      minorCheckbox.addEventListener("change", function(e) {
          console.log("État de la checkbox mineur:", e.target.checked);
          
          // Mettre à jour la visibilité immédiatement si nous sommes à l'étape Documents
          if (currentStep === 2) {
              showStep(currentStep); // Re-afficher l'étape pour appliquer les changements
          }
          
          // Stocker l'état dans localStorage pour le récupérer plus tard
          localStorage.setItem('patientMineur', e.target.checked);
      });
      
      // Récupérer l'état précédent s'il existe
      const savedState = localStorage.getItem('patientMineur');
      if (savedState !== null) {
          minorCheckbox.checked = (savedState === 'true');
          console.log("État restauré:", minorCheckbox.checked);
      }
  } else {
      console.warn("Checkbox 'is_minor' non trouvée");
  }
  
  // Toggle assurance "Autre"
  const insuranceSelect = document.getElementById("insurance_name");
  const insuranceOther = document.getElementById("insurance_name_other");
  
  if (insuranceSelect && insuranceOther) {
      insuranceSelect.addEventListener("change", function(e) {
          insuranceOther.style.display = e.target.value === "Autre" ? "block" : "none";
      });
      
      // Initialiser l'état
      if (insuranceSelect.value === "Autre") {
          insuranceOther.style.display = "block";
      }
  }
  
  // ===== FORMATAGE ET VALIDATION DU NIR =====
  const nirInput = document.querySelector('input[name="social_number"]');
  
  function formatNIR(value) {
      value = value.replace(/\D/g, '');
      value = value.substring(0, 15);
      
      let formatted = '';
      if (value.length > 0) {
          formatted = value.substring(0, 1);
          if (value.length > 1) formatted += ' ' + value.substring(1, 3);
          if (value.length > 3) formatted += ' ' + value.substring(3, 5);
          if (value.length > 5) formatted += ' ' + value.substring(5, 7);
          if (value.length > 7) formatted += ' ' + value.substring(7, 10);
          if (value.length > 10) formatted += ' ' + value.substring(10, 13);
          if (value.length > 13) formatted += ' ' + value.substring(13, 15);
      }
      
      return formatted;
  }
  
  function isValidNIR(nir) {
      nir = nir.replace(/\s/g, '');
      
      if (nir.length !== 15) {
          console.log("NIR invalide: longueur incorrecte", nir.length);
          return false;
      }
      
      const regex = /^[12]\d{2}(0[1-9]|1[0-2])\d{10}$/;
      return regex.test(nir);
  }
  
  if (nirInput) {
      nirInput.addEventListener('input', function(e) {
          let cursorPosition = e.target.selectionStart;
          let value = e.target.value;
          
          const beforeFormat = value.substring(0, cursorPosition).replace(/\D/g, '').length;
          let formattedValue = formatNIR(value);
          
          e.target.value = formattedValue;
          
          let newCursorPosition = 0;
          let charCount = 0;
          
          for (let i = 0; i < formattedValue.length && charCount < beforeFormat; i++) {
              if (/\d/.test(formattedValue[i])) {
                  charCount++;
              }
              newCursorPosition = i + 1;
          }
          
          while (newCursorPosition < formattedValue.length && formattedValue[newCursorPosition] === ' ') {
              newCursorPosition++;
          }
          
          e.target.setSelectionRange(newCursorPosition, newCursorPosition);
      });
      
      nirInput.addEventListener('blur', function() {
          const value = this.value;
          const errorElement = document.getElementById('nirError');
          
          if (value.trim()) {
              if (!isValidNIR(value)) {
                  const cleanNir = value.replace(/\s/g, '');
                  errorElement.textContent = `Le NIR doit contenir 15 chiffres. Format attendu: 1 05 01 94 068 055 34. Vous avez entré ${cleanNir.length} chiffre(s).`;
                  errorElement.style.display = 'block';
                  this.classList.add('field-error');
              } else {
                  errorElement.style.display = 'none';
                  this.classList.remove('field-error');
              }
          }
      });
      
      nirInput.addEventListener('input', function() {
          const errorElement = document.getElementById('nirError');
          errorElement.style.display = 'none';
          this.classList.remove('field-error');
      });
  }
  
  // Validation des étapes
  function validateStep(stepIndex) {
      const step = steps[stepIndex];
      let isValid = true;
      
      // Réinitialiser les erreurs
      step.querySelectorAll('.error-message').forEach(error => {
          error.style.display = 'none';
      });
      
      step.querySelectorAll('input, select, textarea').forEach(field => {
          field.classList.remove('field-error');
      });
      
      switch(stepIndex) {
          case 0: // Étape Patient
              const isNewPatient = document.querySelector('input[name="new_patient"]:checked')?.value === '1';
              
              if (isNewPatient) {
                  const requiredFields = step.querySelectorAll('input[required], select[required]');
                  const missingFields = [];
                  
                  requiredFields.forEach(field => {
                      if (field.name !== 'livret_famille' && !field.value.trim()) {
                          field.classList.add('field-error');
                          isValid = false;
                          missingFields.push(field.getAttribute('placeholder') || field.name || 'champ obligatoire');
                      }
                  });
                  
                  const nirInput = document.querySelector('input[name="social_number"]');
                  if (nirInput && nirInput.value) {
                      if (!isValidNIR(nirInput.value)) {
                          nirInput.classList.add('field-error');
                          document.getElementById('nirError').style.display = 'block';
                          isValid = false;
                      }
                  }
                  
                  const phoneInput = document.querySelector('input[name="phone"]');
                  if (phoneInput && phoneInput.value) {
                      const phoneValue = phoneInput.value.replace(/\D/g, '');
                      if (!/^\d{10}$/.test(phoneValue)) {
                          phoneInput.classList.add('field-error');
                          document.getElementById('phoneError').style.display = 'block';
                          isValid = false;
                      }
                  }
                  
                  const emailInput = document.querySelector('input[name="email"]');
                  if (emailInput && emailInput.value) {
                      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value)) {
                          emailInput.classList.add('field-error');
                          document.getElementById('emailError').style.display = 'block';
                          isValid = false;
                      }
                  }
                  
                  const zipInput = document.querySelector('input[name="zip"]');
                  if (zipInput && zipInput.value) {
                      if (!/^\d{5}$/.test(zipInput.value)) {
                          zipInput.classList.add('field-error');
                          document.getElementById('zipError').style.display = 'block';
                          isValid = false;
                      }
                  }
                  
                  if (!isValid && missingFields.length > 0) {
                      alert("Veuillez remplir tous les champs obligatoires : " + missingFields.join(", "));
                  }
                  
              } else {
                  const patientSelect = document.querySelector('select[name="patient_social"]');
                  if (patientSelect && !patientSelect.value) {
                      patientSelect.classList.add('field-error');
                      isValid = false;
                      alert("Veuillez sélectionner un patient existant");
                  }
              }
              break;
              
          case 1: // Étape Admission
              const requiredAdmissionFields = step.querySelectorAll('[required]');
              const missingAdmissionFields = [];
              
              requiredAdmissionFields.forEach(field => {
                  if (!field.value.trim()) {
                      field.classList.add('field-error');
                      isValid = false;
                      missingAdmissionFields.push(field.getAttribute('placeholder') || field.name || 'champ obligatoire');
                  }
              });
              
              if (!isValid && missingAdmissionFields.length > 0) {
                  alert("Veuillez remplir tous les champs obligatoires de l'admission : " + missingAdmissionFields.join(", "));
              }
              break;
              
          case 2: // Étape Documents
              const isPatientMinor = minorCheckbox ? minorCheckbox.checked : false;
              console.log("Validation étape Documents - Patient mineur:", isPatientMinor);
              
              if (isPatientMinor) {
                  const livretFamilleInput = document.querySelector('input[name="livret_famille"]');
                  if (livretFamilleInput) {
                      console.log("Champ livret_famille trouvé");
                      
                      if (livretFamilleInput.files.length === 0) {
                          alert("Pour un patient mineur, le livret de famille est obligatoire");
                          livretFamilleInput.classList.add('field-error');
                          isValid = false;
                      } else {
                          const file = livretFamilleInput.files[0];
                          if (file.type !== 'application/pdf') {
                              alert("Le livret de famille doit être au format PDF");
                              livretFamilleInput.classList.add('field-error');
                              isValid = false;
                          }
                          
                          if (file.size > 5 * 1024 * 1024) {
                              alert("Le livret de famille est trop volumineux (max 5 Mo)");
                              livretFamilleInput.classList.add('field-error');
                              isValid = false;
                          }
                      }
                  } else {
                      console.log("Champ livret_famille non trouvé - Recherche d'alternatives...");
                      // Chercher par placeholder ou label
                      const fileInputs = step.querySelectorAll('input[type="file"]');
                      fileInputs.forEach(input => {
                          if (input.parentElement.textContent.includes('livret') || 
                              input.parentElement.textContent.includes('famille')) {
                              console.log("Champ alternatif trouvé:", input);
                              if (input.files.length === 0) {
                                  alert("Pour un patient mineur, le livret de famille est obligatoire");
                                  input.classList.add('field-error');
                                  isValid = false;
                              }
                          }
                      });
                  }
              }
              
              const otherDocs = ['id_card', 'vital_card', 'insurance_card'];
              otherDocs.forEach(docName => {
                  const docInput = document.querySelector(`input[name="${docName}"]`);
                  if (docInput && docInput.files.length > 0) {
                      const file = docInput.files[0];
                      if (file.type !== 'application/pdf') {
                          alert(`Le document ${docName.replace('_', ' ')} doit être au format PDF`);
                          docInput.classList.add('field-error');
                          isValid = false;
                      }
                      
                      if (file.size > 5 * 1024 * 1024) {
                          alert(`Le document ${docName.replace('_', ' ')} est trop volumineux (max 5 Mo)`);
                          docInput.classList.add('field-error');
                          isValid = false;
                      }
                  }
              });
              break;
      }
      
      return isValid;
  }
  
  // Formatage téléphone
  const phoneInputs = document.querySelectorAll('input[type="tel"]');
  phoneInputs.forEach(input => {
      if (input.name !== 'insurance_number') {
          input.addEventListener('input', function(e) {
              let value = e.target.value.replace(/\D/g, '');
              if (value.length > 0) {
                  value = value.substring(0, 10);
                  if (value.length > 2) {
                      value = value.substring(0, 2) + ' ' + value.substring(2);
                  }
                  if (value.length > 5) {
                      value = value.substring(0, 5) + ' ' + value.substring(5);
                  }
                  if (value.length > 8) {
                      value = value.substring(0, 8) + ' ' + value.substring(8);
                  }
              }
              e.target.value = value;
          });
      }
  });
  
  // Initialiser au chargement
  showStep(currentStep);
  
  // Désactiver les dates futures pour la date de naissance
  const birthdateInput = document.querySelector('input[name="birthdate"]');
  if (birthdateInput) {
      const today = new Date().toISOString().split('T')[0];
      birthdateInput.max = today;
  }
  
  // Gestion de la soumission du formulaire
  const form = document.getElementById('admissionForm');
  if (form) {
      form.addEventListener('submit', function(e) {
          console.log("Validation avant soumission...");
          
          let allValid = true;
          let firstInvalidStep = null;
          
          for (let i = 0; i < steps.length; i++) {
              if (!validateStep(i)) {
                  allValid = false;
                  if (firstInvalidStep === null) {
                      firstInvalidStep = i;
                  }
              }
          }
          
          if (!allValid) {
              e.preventDefault();
              alert("Veuillez corriger les erreurs dans le formulaire avant de soumettre.");
              if (firstInvalidStep !== null) {
                  showStep(firstInvalidStep);
              }
          } else if (!confirm("Êtes-vous sûr de vouloir enregistrer cette pré-admission ?")) {
              e.preventDefault();
          } else {
              console.log("Formulaire soumis avec succès");
          }
      });
  }
  
  console.log("Formulaire de pré-admission initialisé avec succès");
});