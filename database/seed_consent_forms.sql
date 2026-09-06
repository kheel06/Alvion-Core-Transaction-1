-- =====================================================
-- Consent Forms: templates and signed forms for
-- modules/general/consent_forms.php (Templates + Signed Forms tabs).
-- Run after: general_modules.sql (creates consent_form_templates, signed_consent_forms),
--            seed_admin_data.sql (users, patients). patient_id in signed_consent_forms = users.id.
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- =====================================================
-- 1. CONSENT FORM TEMPLATES
-- =====================================================
INSERT INTO `consent_form_templates` (`id`, `template_name`, `form_type`, `template_content`, `version`, `status`, `created_by`, `created_at`) VALUES
(1, 'General Treatment Consent', 'general', 'I hereby consent to receive medical treatment, procedures, and care as deemed necessary by the attending physician and healthcare team at this facility. I understand the nature and purpose of the treatment and have been informed of the risks and alternatives. I may withdraw this consent at any time.', '1.0', 'active', 1, NOW()),
(2, 'Surgery and Anesthesia Consent', 'surgery', 'I consent to the surgical procedure(s) and anesthesia as discussed with my doctor. I understand the risks, benefits, and possible complications. I authorize the healthcare team to perform such procedures as they consider necessary during the operation.', '1.0', 'active', 1, NOW()),
(3, 'Release of Medical Records (HIPAA)', 'privacy', 'I authorize the release of my medical records and health information to the designated parties for the purpose of continuity of care, insurance claims, or as otherwise specified. This authorization complies with applicable privacy regulations.', '1.0', 'active', 1, NOW()),
(4, 'Telehealth Consultation Consent', 'telehealth', 'I consent to participate in telehealth consultations. I understand that care will be provided via video/phone and that the same standards of care apply. I consent to the use of the designated platform and to the recording of the encounter if applicable.', '1.0', 'active', 1, NOW()),
(5, 'Minor Consent (Parent/Guardian)', 'minor', 'As parent/legal guardian, I consent to the treatment and procedures outlined for the minor patient. I have been informed of the risks and alternatives and agree to the plan of care.', '1.0', 'active', 1, NOW())
ON DUPLICATE KEY UPDATE `template_name` = VALUES(`template_name`), `template_content` = VALUES(`template_content`), `version` = VALUES(`version`);

-- =====================================================
-- 2. SIGNED CONSENT FORMS
-- patient_id = users.id (e.g. 2 = patient user); signed_by = users.id (staff/doctor)
-- =====================================================
INSERT INTO `signed_consent_forms` (`template_id`, `patient_id`, `signed_at`, `signed_by`, `witness_name`, `notes`, `status`, `created_at`) VALUES
(1, 2, DATE_SUB(NOW(), INTERVAL 5 DAY), 11, 'Jennifer Lopez', 'Signed at registration.', 'active', NOW()),
(2, 2, DATE_SUB(NOW(), INTERVAL 3 DAY), 3, 'Ricardo Santos, MD', 'Pre-op consent for scheduled procedure.', 'active', NOW()),
(3, 2, DATE_SUB(NOW(), INTERVAL 2 DAY), 13, 'Mark Dela Cruz', 'PhilHealth and insurance release.', 'active', NOW()),
(4, 2, DATE_SUB(NOW(), INTERVAL 1 DAY), 4, 'Maria Reyes, MD', 'Teleconsult consent.', 'active', NOW()),
(1, 2, DATE_SUB(NOW(), INTERVAL 10 DAY), 11, 'Kristine Ramos', 'Initial general consent.', 'active', NOW())
ON DUPLICATE KEY UPDATE `signed_at` = VALUES(`signed_at`);

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
