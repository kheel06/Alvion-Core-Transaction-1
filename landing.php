<?php
// This is a simple PHP file that outputs HTML. In a real setup, you'd integrate with a CMS or framework.
// For demonstration, it's named landing.php as requested.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once 'config/config.php';

// Ensure doctors table has all necessary columns
function ensureDoctorsTableColumns($db) {
    if (!$db) {
        return false;
    }
    
    try {
        $columns_to_add = [
            'first_name' => "VARCHAR(100) DEFAULT NULL",
            'last_name' => "VARCHAR(100) DEFAULT NULL",
            'profile_image' => "VARCHAR(255) DEFAULT NULL",
            'schedule' => "VARCHAR(100) DEFAULT NULL",
            'location' => "VARCHAR(255) DEFAULT NULL",
            'availability' => "VARCHAR(50) DEFAULT 'Available'",
            'availability_color' => "VARCHAR(20) DEFAULT 'gray'"
        ];
        
        foreach ($columns_to_add as $column => $definition) {
            try {
                $stmt = $db->query("SHOW COLUMNS FROM doctors LIKE '$column'");
                if ($stmt && $stmt->rowCount() === 0) {
                    $db->exec("ALTER TABLE doctors ADD COLUMN $column $definition");
                }
            } catch (PDOException $e) {
                error_log("Could not add column $column to doctors table: " . $e->getMessage());
            }
        }
        return true;
    } catch (PDOException $e) {
        error_log("Error ensuring doctors table columns: " . $e->getMessage());
        return false;
    }
}

// Fetch doctors from database with all information
function getDoctors($db) {
    if (!$db) {
        error_log("Database connection is null in getDoctors()");
        return [];
    }
    
    try {
        // Ensure columns exist
        ensureDoctorsTableColumns($db);
        
        // Build query with column existence checks
        $query = "SELECT 
                    d.id,
                    d.prc_number,
                    d.specialty,
                    d.services,
                    d.years_experience,
                    d.consultation_fee,
                    d.status,
                    d.description,
                    d.education,
                    d.certifications";
        
        // Add optional columns if they exist
        $optional_columns = ['first_name', 'last_name', 'profile_image', 'schedule', 'location', 'availability', 'availability_color'];
        foreach ($optional_columns as $col) {
            try {
                $check = $db->query("SHOW COLUMNS FROM doctors LIKE '$col'");
                if ($check && $check->rowCount() > 0) {
                    $query .= ", d.$col";
                } else {
                    $query .= ", NULL as $col";
                }
            } catch (PDOException $e) {
                $query .= ", NULL as $col";
            }
        }
        
        $query .= " FROM doctors d
                  WHERE d.status = 1 
                  ORDER BY d.specialty, d.id";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process and enhance doctor data
        foreach ($doctors as &$doctor) {
            // Set defaults for missing fields
            if (empty($doctor['first_name'])) {
                $doctor['first_name'] = 'Dr.';
            }
            if (empty($doctor['last_name'])) {
                $doctor['last_name'] = '';
            }
            
            // Set default image based on PRC number if not provided
            if (empty($doctor['profile_image'])) {
                $prc_images = [
                    'PRC-123456' => 'assets/img/doctors/Dr. Antonio Reyes.png',
                    'PRC-234567' => 'assets/img/doctors/Dr. Maria Isabel Santos.png',
                    'PRC-345678' => 'assets/img/doctors/Dr. Rafael Dela Cruz.png',
                    'PRC-456789' => 'assets/img/doctors/Dr. Liza Marquez.png',
                    'PRC-567890' => 'assets/img/doctors/Dr. Jerome Bautista.png',
                    'PRC-678901' => 'assets/img/doctors/Dr. Aileen Navarro.png'
                ];
                $doctor['image'] = $prc_images[$doctor['prc_number']] ?? 'assets/img/doctor-placeholder.png';
            } else {
                $doctor['image'] = $doctor['profile_image'];
            }
            
            // Real-time schedule and availability from doctor_schedules and appointments_public
            $realtime = getDoctorRealtimeScheduleAndAvailability($db, (int) $doctor['id']);
            $doctor['schedule'] = $realtime['schedule'];
            $doctor['availability'] = $realtime['availability'];
            $doctor['availability_color'] = $realtime['availability_color'];
            $doctor['location'] = $doctor['location'] ?? 'Main Clinic';
            
            // Try to join with users table to get name and profile picture if available
            try {
                $user_query = "SELECT u.id, u.first_name, u.last_name, u.profile_picture, u.specialization
                              FROM users u
                              INNER JOIN roles r ON u.role_id = r.id
                              WHERE r.role_name = 'doctor' 
                              AND u.status = 'active'
                              AND (u.email LIKE CONCAT('%', :prc, '%') 
                                   OR CONCAT(u.first_name, ' ', u.last_name) LIKE CONCAT('%', :prc, '%'))
                              LIMIT 1";
                $user_stmt = $db->prepare($user_query);
                $user_stmt->bindValue(':prc', $doctor['prc_number']);
                $user_stmt->execute();
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // Override with user data if available
                    if (!empty($user['first_name'])) {
                        $doctor['first_name'] = $user['first_name'];
                    }
                    if (!empty($user['last_name'])) {
                        $doctor['last_name'] = $user['last_name'];
                    }
                    if (!empty($user['profile_picture'])) {
                        $doctor['image'] = $user['profile_picture'];
                    }
                    if (!empty($user['specialization'])) {
                        $doctor['specialty'] = $user['specialization'];
                    }
                }
            } catch (PDOException $e) {
                // If join fails, continue with doctor table data
                error_log("Could not join users table for doctor: " . $e->getMessage());
            }
        }
        
        return $doctors;
    } catch (PDOException $e) {
        error_log("Error fetching doctors: " . $e->getMessage());
        return [];
    }
}

// Get specialization filter key
function getSpecializationFilter($specialty) {
    $filters = [
        'Cardiologist' => 'cardiology',
        'Obstetrician & Gynecologist' => 'obstetrics_gynecology',
        'Pediatrician' => 'pediatrics',
        'Neurologist' => 'neurology',
        'Internal Medicine' => 'internal_medicine',
        'Orthopedic Surgeon' => 'orthopedics'
    ];
    return $filters[$specialty] ?? 'all';
}

// Render doctor cards
function renderDoctorCards($doctors) {
    $html = '';
    $delay = 100;
    $gradientColors = ['teal', 'blue', 'purple', 'green', 'yellow', 'red'];
    $colorIndex = 0;
    
    foreach ($doctors as $doctor) {
        $specializationFilter = getSpecializationFilter($doctor['specialty']);
        $gradientColor = $gradientColors[$colorIndex % count($gradientColors)];
        $colorIndex++;
        
        // Get doctor name from database
        $first_name = $doctor['first_name'] ?? '';
        $last_name = $doctor['last_name'] ?? '';
        $name = trim($first_name . ' ' . $last_name);
        if (empty($name)) {
            $name = $doctor['specialty'] ?? 'Doctor';
        }
        $full_name = stripos($name, 'Dr.') === 0 ? $name : 'Dr. ' . $name;
        
        // Get image path
        $image = $doctor['image'] ?? 'assets/img/doctor-placeholder.png';
        
        // Get schedule and location
        $schedule = $doctor['schedule'] ?? 'By Appointment';
        $location = $doctor['location'] ?? 'Main Clinic';
        
        // Get availability
        $availability = $doctor['availability'] ?? 'Available';
        $availability_color = $doctor['availability_color'] ?? 'gray';
        
        // Get credentials
        $credentials = $doctor['certifications'] ?? '';
        
        $html .= '
            <div class="doctor-card group bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-2xl transition-all duration-300 fade-in-up delay-' . $delay . ' flex flex-col h-full transform hover:-translate-y-2" data-specialization="' . htmlspecialchars($specializationFilter) . '" data-doctor-id="' . $doctor['id'] . '">
                <div class="relative h-72 bg-gradient-to-br from-' . $gradientColor . '-500 to-' . $gradientColor . '-700 flex items-center justify-center overflow-hidden">
                    <img src="' . htmlspecialchars($image) . '" alt="' . htmlspecialchars($full_name) . '" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                    <div class="absolute inset-0 bg-black/20 group-hover:bg-black/10 transition-colors duration-300"></div>
                    <div class="absolute top-4 right-4">
                        <span class="bg-white/90 text-' . $gradientColor . '-700 text-xs font-semibold px-3 py-1 rounded-full shadow-sm">' . htmlspecialchars($availability) . '</span>
                    </div>
                </div>
                <div class="p-6 flex flex-col flex-grow">
                    <div class="mb-3">
                        <h3 class="text-xl font-bold text-gray-900 mb-1">' . htmlspecialchars($full_name) . '</h3>
                        <p class="text-teal-custom font-semibold mb-1">' . htmlspecialchars($doctor['specialty'] ?? 'Specialist') . '</p>
                        ' . (!empty($credentials) ? '<p class="text-sm text-gray-500">' . htmlspecialchars($credentials) . '</p>' : '') . '
                    </div>
                    ' . (!empty($doctor['description']) ? '<p class="text-gray-600 mb-4 text-sm flex-grow leading-relaxed">' . htmlspecialchars($doctor['description']) . '</p>' : '') . '
                    <div class="space-y-3 mt-auto">
                        <div class="flex items-center text-sm text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 flex-shrink-0"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>
                            <span>' . htmlspecialchars($schedule) . '</span>
                        </div>
                        <div class="flex items-center justify-between pt-2">
                            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">' . htmlspecialchars($location) . '</span>
                            <button type="button" onclick="openDoctorProfile(' . $doctor['id'] . ')" class="text-teal-600 hover:text-teal-700 font-semibold text-sm flex items-center group/btn">
                                View Profile 
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-1 transition-transform group-hover/btn:translate-x-1"><path d="m9 18 6-6-6-6"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        ';
        
        $delay = ($delay >= 300) ? 100 : $delay + 100;
    }
    
    return $html;
}

// Fetch doctors - check if database connection exists
$doctors = [];
if ($db) {
    $doctors = getDoctors($db);
} else {
    error_log("Database connection failed. Please ensure MySQL is running.");
}

function renderLoginLink(): string
{
    return '<a href="auth/login.php" class="btn btn-teal btn-sm">Login</a>';
}

function renderAppointmentSchedulingSection(): string
{
    return '
        <section id="appointment" class="py-16 bg-gray-50">
            <div class="max-w-screen-xl mx-auto px-4">
                <div class="text-center max-w-3xl mx-auto">
                    <h2 class="text-3xl md:text-4xl font-bold mb-4 text-teal-custom">Reserve Your Walk-in Slot</h2>
                    <p class="text-lg text-gray-700 mb-6">
                        Reserve your preferred time slot for walk-in consultation with our board-certified specialists. 
                        Select your doctor and preferred schedule, then check in at the clinic on your selected date and time.
                    </p>
                    <ul class="space-y-3 text-gray-700 mb-8 text-left max-w-2xl mx-auto">
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-teal-custom mr-3 text-xl mt-0.5"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                            <span>Reserve your preferred time slot in advance.</span>
                        </li>
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-teal-custom mr-3 text-xl mt-0.5"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                            <span>Instant confirmation with reservation details via email.</span>
                        </li>
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-teal-custom mr-3 text-xl mt-0.5"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                            <span>Check in at the clinic on your selected date and time.</span>
                        </li>
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-teal-custom mr-3 text-xl mt-0.5"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                            <span>Priority queue for reserved walk-in slots.</span>
                        </li>
                    </ul>
                    <button type="button" data-modal-target="appointment-modal" data-modal-toggle="appointment-modal" class="btn btn-teal btn-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="m9 16 2 2 4-4"></path></svg>
                        Reserve Walk-in Slot
                    </button>
                </div>
            </div>
        </section>
    ';
}

function renderAppointmentModal($doctors): string
{
    return '
        <!-- Appointment Booking Modal -->
        <div id="appointment-modal" tabindex="-1" aria-hidden="true" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeAppointmentModal()"></div>
            <!-- Modal content -->
            <div class="relative w-full max-w-3xl max-h-[90vh] bg-white rounded-lg shadow-xl overflow-y-auto">
                <!-- Modal header -->
                <div class="flex items-center justify-between p-5 border-b rounded-t">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">Make an Appointment</h3>
                            <p class="text-sm text-gray-500 mt-1">Select a doctor, choose your visit type, and book a confirmed slot.</p>
                        </div>
                        <button type="button" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center" onclick="closeAppointmentModal()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-xl"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                            <span class="sr-only">Close modal</span>
                        </button>
                    </div>
                    <!-- Modal body -->
                    <div class="p-6 overflow-y-auto max-h-[calc(100vh-16rem)] modal-scrollable">
                        <form method="POST" class="space-y-6" id="appointment-form" novalidate>
                            <div id="appointment-feedback" class="hidden text-sm rounded-lg border px-4 py-3"></div>
                            <div id="selected-doctor-info" class="border border-teal-100 bg-teal-50/70 rounded-lg p-4 flex items-start justify-between gap-4 hidden">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1">Booking with</p>
                                    <p id="selected-doctor-name" class="text-lg font-semibold text-gray-900"></p>
                                    <p id="selected-doctor-specialization" class="text-sm text-gray-600"></p>
                                </div>
                                <button type="button" id="open-doctor-suggestions" class="text-sm text-teal-600 hover:text-teal-700 font-semibold flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7 11 2 2 4-4"></path><path d="M5 5h12"></path><path d="M5 19h12"></path></svg>
                                    Select other doctors
                                </button>
                            </div>
                            <div id="doctor-suggestions" class="hidden border border-dashed border-gray-300 rounded-lg p-4 bg-gray-50/70">
                                <div class="mb-3">
                                    <p class="text-sm font-semibold text-gray-900">Other available doctors</p>
                                    <p class="text-xs text-gray-600">Choose someone else and we&rsquo;ll refresh the schedule.</p>
                                </div>
                                <div id="doctor-suggestions-grid" class="grid grid-cols-1 md:grid-cols-3 gap-3"></div>
                            </div>
                            <!-- Step 1: Patient Essentials -->
                            <div class="border-b pb-4 space-y-4">
                                <h4 class="text-lg font-semibold text-gray-900">Patient Essentials</h4>
                                <div>
                                    <label for="appt-fullname" class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                                    <input type="text" id="appt-fullname" name="full_name" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-teal-custom focus:border-teal-custom" placeholder="e.g., Juan Dela Cruz" required>
                                    <input type="hidden" name="first_name" id="appt-firstname-hidden">
                                    <input type="hidden" name="last_name" id="appt-lastname-hidden">
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="appt-contact" class="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label>
                                        <input type="tel" id="appt-contact" name="contact_number" placeholder="09xx xxx xxxx" pattern="09[0-9]{2} [0-9]{3} [0-9]{4}" maxlength="13" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-teal-custom focus:border-teal-custom" required>
                                        <p class="mt-1 text-xs text-gray-500">Format: 09xx xxx xxxx (e.g., 0912 345 6789)</p>
                                    </div>
                                    <div>
                                        <label for="appt-email" class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                                        <input type="email" id="appt-email" name="email" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-teal-custom focus:border-teal-custom" required>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="appt-dob" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth *</label>
                                        <input type="date" id="appt-dob" name="date_of_birth" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-teal-custom focus:border-teal-custom" required>
                                    </div>
                                    <div>
                                        <label for="appt-hmo" class="block text-sm font-medium text-gray-700 mb-1">HMO / Insurance Provider (Optional)</label>
                                        <input type="text" id="appt-hmo" name="hmo_provider" placeholder="Maxicare, Intellicare, etc." class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-teal-custom focus:border-teal-custom">
                                    </div>
                                </div>
                            </div>


                            <!-- Visit Type Selection -->
                            <div class="border-b pb-4">
                                <h4 class="text-lg font-semibold text-gray-900 mb-4">Appointment Type</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <label class="border rounded-lg p-4 cursor-pointer flex items-start gap-3 hover:border-teal-500 transition">
                                        <input type="radio" name="visit_type" value="in_person_consultation" class="mt-1" checked>
                                        <div>
                                            <p class="font-semibold text-gray-900 text-sm">In-Person Consultation</p>
                                            <p class="text-xs text-gray-600">Clinic visit with on-site diagnostics.</p>
                                        </div>
                                    </label>
                                    <label class="border rounded-lg p-4 cursor-pointer flex items-start gap-3 hover:border-teal-500 transition">
                                        <input type="radio" name="visit_type" value="teleconsultation_video" class="mt-1">
                                        <div>
                                            <p class="font-semibold text-gray-900 text-sm">Teleconsultation (Video)</p>
                                            <p class="text-xs text-gray-600">Secure virtual visit with synced telehealth list.</p>
                                        </div>
                                    </label>
                                    <label class="border rounded-lg p-4 cursor-pointer flex items-start gap-3 hover:border-teal-500 transition">
                                        <input type="radio" name="visit_type" value="walkin_express" class="mt-1">
                                        <div>
                                            <p class="font-semibold text-gray-900 text-sm">Walk-In (Express)</p>
                                            <p class="text-xs text-gray-600">Priority queue reservation for same-day visits.</p>
                                        </div>
                                    </label>
                                </div>
                                <div id="telehealth-wrapper" class="mt-4 hidden">
                                    <label for="telehealth-platform" class="block text-sm font-medium text-gray-700 mb-1">Telehealth Platform</label>
                                    <select id="telehealth-platform" name="telehealth_platform" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-teal-custom focus:border-teal-custom">
                                        <option value="">Select platform</option>
                                        <option value="zoom">Zoom</option>
                                        <option value="google_meet">Google Meet</option>
                                        <option value="teams">Microsoft Teams</option>
                                        <option value="alvion_virtual_clinic">Alvion Virtual Clinic</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Step 4: Choose Schedule -->
                            <div class="border-b pb-4">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-lg font-semibold text-gray-900">Date & Time</h4>
                                    <p class="text-sm text-gray-500 flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>
                                        Filters only available slots in real-time
                                    </p>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="appt-date" class="block text-sm font-medium text-gray-700 mb-1">Preferred Date *</label>
                                        <input type="date" id="appt-date" name="appointment_date" min="' . date('Y-m-d') . '" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-teal-custom focus:border-teal-custom" required>
                                    </div>
                                    <div>
                                        <label for="appt-slot" class="block text-sm font-medium text-gray-700 mb-1">Available Time Slots *</label>
                                        <select id="appt-slot" name="appointment_slot" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-teal-custom focus:border-teal-custom" required>
                                            <option value="">Select a date to load slots</option>
                                        </select>
                                        <input type="hidden" name="appointment_time" id="appt-time-hidden">
                                        <p class="mt-1 text-xs text-gray-500">Slots are refreshed using the doctor&rsquo;s live schedule.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Information -->
                            <div>
                                <label for="appt-reason" class="block text-sm font-medium text-gray-700 mb-1">Reason for Visit (Optional)</label>
                                <textarea id="appt-reason" name="reason" rows="3" placeholder="Brief description of your concern or symptoms..." class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-teal-custom focus:border-teal-custom"></textarea>
                            </div>

                            <input type="hidden" name="booking_channel" value="online">
                            <input type="hidden" name="doctor_id" value="">
                            <div class="flex items-center justify-end gap-3 pt-4 border-t">
                                <button type="button" onclick="closeAppointmentModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none">
                                    Cancel
                                </button>
                                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-teal-500 rounded-lg hover:bg-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 inline-flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="m9 16 2 2 4-4"></path></svg>
                                    Make Appointment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    ';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alvion Health Network - Trusted Philippine Hospital Care</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/1.8.1/flowbite.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-50">

    <!-- Navigation -->
    <nav class="navbar">
        <div class="max-w-screen-xl flex items-center justify-between mx-auto px-4 py-3">
            <!-- Logo -->
            <a href="#" class="flex items-center flex-shrink-0">
            <img src="assets/img/alvion-logo-removebg.png" alt="Alvion">
            </a>
            
            <!-- Mobile Menu Button -->
            <button data-collapse-toggle="navbar-mobile" type="button" class="inline-flex items-center p-2 w-10 h-10 justify-center text-sm text-gray-500 rounded-lg md:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200" aria-controls="navbar-mobile" aria-expanded="false">
                <span class="sr-only">Open main menu</span>
                <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 17 14">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 1h15M1 7h15M1 13h15"/>
                </svg>
            </button>
            
            <!-- Navigation Links and Login Button - Right -->
            <div class="hidden md:flex md:items-center md:gap-6">
                <ul class="nav-list">
                    <li>
                        <a href="#home" class="nav-link nav-link--active" aria-current="page">Home</a>
                    </li>
                    <li>
                        <a href="#services" class="nav-link">Services</a>
                    </li>
                    <li>
                        <a href="#find-doctor" class="nav-link">Find a Doctor</a>
                    </li>
                    <li>
                        <a href="#about" class="nav-link">About</a>
                    </li>
                    <li>
                        <a href="#contact" class="nav-link">Contact</a>
                    </li>
                </ul>
                <?php echo renderLoginLink(); ?>
            </div>
            
            <!-- Mobile Menu -->
            <div class="hidden md:hidden w-full absolute top-full left-0 bg-white border-b border-gray-200 shadow-lg" id="navbar-mobile">
                <ul class="flex flex-col p-4 space-y-3">
                    <li>
                        <a href="#home" class="nav-link nav-link--active" aria-current="page">Home</a>
                    </li>
                    <li>
                        <a href="#services" class="nav-link">Services</a>
                    </li>
                    <li>
                        <a href="#about" class="nav-link">About</a>
                    </li>
                    <li>
                        <a href="#find-doctor" class="nav-link">Find a Doctor</a>
                    </li>
                    <li>
                        <a href="#contact" class="nav-link">Contact</a>
                    </li>
                    <li class="pt-2">
                        <?php echo renderLoginLink(); ?>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="text-white min-h-screen py-20 md:py-28 text-center relative bg-cover bg-center bg-no-repeat flex items-center" style="background-image: linear-gradient(rgba(0, 150, 136, 0.8), rgba(84, 110, 122, 0.8)), url('assets/img/home-bg.png');">
        <div class="max-w-screen-xl mx-auto px-4 relative z-10 w-full">
            <span class="inline-flex items-center px-4 py-1 mb-4 text-sm font-semibold bg-white/20 rounded-full uppercase tracking-widest fade-in-up">Philippine Excellence in Care</span>
            <h1 class="text-4xl md:text-6xl font-bold mb-6 leading-tight fade-in-up delay-100">World-Class Healthcare for Every Filipino Family</h1>
            <p class="text-lg md:text-xl mb-8 max-w-3xl mx-auto fade-in-up delay-200">
                From preventive screenings to complex surgery, Alvion connects you to board-certified specialists,
                advanced diagnostics, and compassionate recovery support across our hospital network.
                Experience streamlined admissions, telehealth follow-ups, and real-time care coordination 24/7.
            </p>
            <div class="flex flex-col md:flex-row items-center justify-center gap-4 fade-in-up delay-300">
                <a href="#find-doctor" class="btn btn-teal btn-lg inline-flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                    View Doctors
                </a>
                <a href="#services" class="inline-flex items-center text-white font-semibold hover:underline">
                    Explore Departments
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-2 text-xl inline"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                </a>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-16 bg-white">
        <div class="max-w-screen-xl mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-4 text-teal-custom fade-in-up">Integrated Centers of Excellence</h2>
            <p class="text-center text-gray-600 max-w-3xl mx-auto mb-12 fade-in-up delay-100">
                Multidisciplinary teams work hand-in-hand to deliver precise diagnostics, evidence-based treatment, and continuous
    wellness programs tailored to the needs of Filipino patients and their families.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white p-6 rounded-lg shadow-lg text-center fade-in-up delay-100">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-4xl text-teal-custom mb-4 mx-auto"><path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"></path><path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4"></path><circle cx="20" cy="10" r="2"></circle></svg>
                    <h4 class="text-xl font-semibold mb-2 text-[#273043]">Adult & Family Medicine</h4>
                    <p class="text-gray-600">Primary care physicians provide preventive screenings, chronic disease management, and lifestyle coaching for every stage of life.</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-lg text-center fade-in-up delay-200">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-4xl text-red-500 mb-4 mx-auto"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.51 4.04 3 5.5l7 7Z"></path><path d="M3.22 12H9.5l.5-1 2 4.5 2-7 1.5 3.5h5.27"></path></svg>
                    <h4 class="text-xl font-semibold mb-2 text-[#273043]">Cardiac & Vascular Institute</h4>
                    <p class="text-gray-600">Diagnostic cardiology, cath-lab interventions, cardiac surgery, and post-operative rehabilitation under one integrated program.</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-lg text-center fade-in-up delay-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-4xl text-green-500 mb-4 mx-auto"><path d="M9 12h6"></path><path d="M9 16h6"></path><path d="M12 3a6 6 0 0 0-6 6v7a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V9a6 6 0 0 0-6-6Z"></path><circle cx="12" cy="19" r="1"></circle></svg>
                    <h4 class="text-xl font-semibold mb-2 text-[#273043]">Women & Child Health Pavilion</h4>
                    <p class="text-gray-600">Comprehensive prenatal, birthing, neonatal, and pediatric subspecialty services designed around the Filipino family.</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-lg text-center fade-in-up delay-100">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-4xl text-yellow-500 mb-4 mx-auto"><path d="M12 5a3 3 0 1 0-5.997.142 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588 4 4 0 0 0 7.636 2.106 3.2 3.2 0 0 0 .164-.546 4 4 0 0 0 2.526-5.77 4 4 0 0 0-.556-6.588A4 4 0 0 0 12 5Z"></path><path d="M12 5v14"></path><path d="M12 5a3 3 0 1 1 5.997.142 4 4 0 0 1 2.526 5.77 4 4 0 0 1-.556 6.588 4 4 0 0 1-7.636 2.106 3.2 3.2 0 0 1-.164-.546 4 4 0 0 1-2.526-5.77 4 4 0 0 1 .556-6.588A4 4 0 0 1 12 5Z"></path></svg>
                    <h4 class="text-xl font-semibold mb-2 text-[#273043]">Neuroscience & Stroke Center</h4>
                    <p class="text-gray-600">Rapid-response stroke care, neurodiagnostics, and neurosurgery supported by a dedicated neuro-critical team.</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-lg text-center fade-in-up delay-200">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-4xl text-blue-500 mb-4 mx-auto"><path d="M5 7h14"></path><path d="M5 12h14"></path><path d="M5 17h14"></path><path d="M4 4h16v16H4z"></path></svg>
                    <h4 class="text-xl font-semibold mb-2 text-[#273043]">Advanced Diagnostics</h4>
                    <p class="text-gray-600">Digital imaging, MRI, CT, ultrasound, and laboratory diagnostics seamlessly feeding into your electronic health record.</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-lg text-center fade-in-up delay-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-4xl text-gray-500 mb-4 mx-auto"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path><circle cx="11" cy="11" r="3"></circle></svg>
                    <h4 class="text-xl font-semibold mb-2 text-[#273043]">Clinical Pharmacy & Infusion</h4>
                    <p class="text-gray-600">Accredited pharmacists delivering medication therapy management, specialty compounding, and infusion services with bedside counseling.</p>
                </div>
            </div>
        </div>
    </section>

  <!-- Find a Doctor Section -->
<section id="find-doctor" class="py-16 bg-white">
    <div class="max-w-screen-xl mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-bold mb-4 text-teal-custom fade-in-up">Find a Doctor</h2>
            <p class="text-lg text-gray-600 max-w-3xl mx-auto fade-in-up delay-100">
                Search for board-certified specialists by specialization. View doctor profiles, check availability, and book appointments directly.
            </p>
            
            <!-- Specialization Filter -->
            <div class="flex flex-wrap justify-center gap-3 mt-8 mb-6 fade-in-up delay-200">
                <button class="filter-btn active" data-filter="all">All Specialties</button>
                <button class="filter-btn" data-filter="cardiology">Cardiology</button>
                <button class="filter-btn" data-filter="obstetrics_gynecology">OB-GYN</button>
                <button class="filter-btn" data-filter="pediatrics">Pediatrics</button>
                <button class="filter-btn" data-filter="neurology">Neurology</button>
                <button class="filter-btn" data-filter="internal_medicine">Internal Medicine</button>
                <button class="filter-btn" data-filter="orthopedics">Orthopedics</button>
            </div>
        </div>
        
        <!-- Doctor Cards Grid -->
        <div id="doctors-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php echo renderDoctorCards($doctors); ?>
        </div>
    </div>
</section>

    <!-- Doctor Profile Modal -->
    <div id="doctor-profile-modal" tabindex="-1" aria-hidden="true" class="hidden fixed inset-0 z-50 overflow-y-auto overflow-x-hidden flex items-center justify-center w-full h-full bg-black bg-opacity-50">
        <div class="relative p-4 w-full max-w-4xl max-h-[90vh]">
            <div class="relative bg-white rounded-lg shadow-xl">
                <div class="flex items-center justify-between p-5 border-b rounded-t">
                    <h3 class="text-xl font-bold text-gray-900">Doctor Profile</h3>
                    <button type="button" onclick="closeDoctorProfile()" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-xl"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto max-h-[calc(90vh-8rem)]" id="doctor-profile-content">
                    <!-- Doctor profile content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- About Section -->
    <section id="about" class="py-16 bg-light-blue-gray">
        <div class="max-w-screen-xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                <div class="fade-in-left">
                    <h2 class="text-3xl font-bold mb-4">About Alvion</h2>
                    <p class="mb-4 text-gray-700">Alvion is a Filipino-managed hospital network that blends compassionate bedside care with smart health technology. Our flagship tertiary center in Bonifacio Global City works hand-in-hand with satellite clinics and partner hospitals in key provinces, giving patients a consistent standard of care wherever they are.</p>
                    <p class="text-gray-700">We follow DOH, PhilHealth, and ISO-aligned protocols, provide bilingual (English and Filipino) assistance, and maintain unified electronic health records so every diagnosis, procedure, and recovery milestone is coordinated and transparent.</p>
                </div>
                <div class="rounded-lg shadow-lg overflow-hidden h-64 md:h-80 lg:h-96 fade-in-right">
                    <img src="assets/img/alvion-building.png" alt="Hospital Building" class="w-full h-full object-cover">
                </div>
            </div>
        </div>
    </section>


    <!-- Contact Section -->
    <section id="contact" class="py-16 bg-white">
        <div class="max-w-screen-xl mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12 fade-in-up">Contact Alvion</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="fade-in-left">
                    <h4 class="text-xl font-semibold mb-4 text-teal-custom">Get in Touch</h4>
                        <p class="mb-2 text-gray-700"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg> 11th Ave. corner 32nd St., Bonifacio Global City, Taguig, Metro Manila</p>
                        <p class="mb-2 text-gray-700"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg> Metro Manila CareLine: (02) 8888-ALVN (2586)</p>
                        <p class="mb-2 text-gray-700"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg> Provincial Hotline: 1-800-10-ALVION</p>
                        <p class="mb-2 text-gray-700"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg> care@alvionhealth.ph</p>
                        <p class="text-gray-700"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Emergency & Telehealth Command Center: Open 24/7</p>
                </div>
                <div class="fade-in-right">
                    <form class="space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" id="name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-teal-custom focus:border-teal-custom" required>
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" id="email" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-teal-custom focus:border-teal-custom" required>
                        </div>
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700">Message</label>
                            <textarea id="message" rows="4" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-teal-custom focus:border-teal-custom" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-teal btn-lg btn-full">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-gray-300 py-12">
        <div class="max-w-screen-xl mx-auto px-4">
            <!-- Top Section: Four Columns -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-8">
                <!-- Column 1: Get Started -->
                <div>
                    <h3 class="text-white font-bold text-lg mb-4">Get Started</h3>
                    <ul class="space-y-2">
                        <li>
                            <a href="auth/register.php" class="text-gray-400 hover:text-white transition-colors">Sign Up</a>
                        </li>
                        <li>
                            <a href="#appointment" class="text-gray-400 hover:text-white transition-colors">Reserve Walk-in Slot</a>
                        </li>
                        <li>
                            <a href="#services" class="text-gray-400 hover:text-white transition-colors">Our Services</a>
                        </li>
                    </ul>
                </div>

                <!-- Column 2: Get Help -->
                <div>
                    <h3 class="text-white font-bold text-lg mb-4">Get Help</h3>
                    <ul class="space-y-2">
                        <li>
                            <a href="#about" class="text-gray-400 hover:text-white transition-colors">How it works</a>
                        </li>
                        <li>
                            <a href="#contact" class="text-gray-400 hover:text-white transition-colors">FAQ</a>
                        </li>
                        <li>
                            <a href="#contact" class="text-gray-400 hover:text-white transition-colors">Help Desk</a>
                        </li>
                    </ul>
                </div>

                <!-- Column 3: About Us -->
                <div>
                    <h3 class="text-white font-bold text-lg mb-4">About Us</h3>
                    <ul class="space-y-2">
                        <li>
                            <a href="#about" class="text-gray-400 hover:text-white transition-colors">Hospital</a>
                        </li>
                        <li>
                            <a href="#doctors" class="text-gray-400 hover:text-white transition-colors">Our Doctors</a>
                        </li>
                        <li>
                            <a href="#contact" class="text-gray-400 hover:text-white transition-colors">Contact</a>
                        </li>
                    </ul>
                </div>

                <!-- Column 4: Partnerships -->
                <div>
                    <h3 class="text-white font-bold text-lg mb-4">Partnerships</h3>
                    <ul class="space-y-2">
                        <li>
                            <a href="#contact" class="text-gray-400 hover:text-white transition-colors">Alvion Network</a>
                        </li>
                        <li>
                            <a href="#contact" class="text-gray-400 hover:text-white transition-colors">Affiliates</a>
                        </li>
                        <li>
                            <a href="#contact" class="text-gray-400 hover:text-white transition-colors">Contact Sales</a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Bottom Section: Terms, Social Media, Copyright -->
            <div class="border-t border-gray-700 pt-8">
                <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                    <!-- Left: Terms and Privacy -->
                    <div class="flex items-center gap-4">
                        <a href="legal/terms-of-use.php" target="_blank" class="text-gray-400 hover:text-white transition-colors text-sm">Terms of Use</a>
                        <a href="legal/privacy-policy.php" target="_blank" class="text-gray-400 hover:text-white transition-colors text-sm">Privacy Policy</a>
                    </div>

                    <!-- Center: Social Media Icons -->
                    <div class="flex items-center gap-4">
                        <a href="#" class="w-10 h-10 rounded-full border-2 border-white flex items-center justify-center text-white hover:bg-white hover:text-gray-800 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" class="text-lg"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full border-2 border-white flex items-center justify-center text-white hover:bg-white hover:text-gray-800 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" class="text-lg"><path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z"></path></svg>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full border-2 border-white flex items-center justify-center text-white hover:bg-white hover:text-gray-800 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-lg"><rect width="20" height="20" x="2" y="2" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                        </a>
                    </div>

                    <!-- Right: Copyright -->
                    <div class="text-gray-400 text-sm">
                        &copy; <?php echo date('Y'); ?> Alvion
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <?php echo renderAppointmentModal($doctors); ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/1.8.1/flowbite.min.js"></script>
    <script>
        // Show scrollbar only when scrolling
        document.addEventListener('DOMContentLoaded', function() {
            const modalScrollable = document.querySelector('.modal-scrollable');
            if (modalScrollable) {
                let scrollTimeout;
                
                modalScrollable.addEventListener('scroll', function() {
                    // Add class to show scrollbar during scroll
                    this.classList.add('scrolling');
                    
                    // Clear existing timeout
                    clearTimeout(scrollTimeout);
                    
                    // Hide scrollbar after scrolling stops (500ms delay)
                    scrollTimeout = setTimeout(() => {
                        this.classList.remove('scrolling');
                    }, 500);
                });
                
                // Also show on hover
                modalScrollable.addEventListener('mouseenter', function() {
                    this.classList.add('hovering');
                });
                
                modalScrollable.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('scrolling')) {
                        this.classList.remove('hovering');
                    }
                });
            }

            // Navbar scroll effect
            const navbar = document.querySelector('.navbar');
            let lastScroll = 0;

            window.addEventListener('scroll', function() {
                const currentScroll = window.pageYOffset;
                
                // Add scrolled class when scrolling down
                if (currentScroll > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
                
                lastScroll = currentScroll;
            });

            // Scroll-triggered animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, observerOptions);

            // Observe all elements with animation classes
            const animatedElements = document.querySelectorAll('.fade-in-up, .fade-in-left, .fade-in-right');
            animatedElements.forEach(el => {
                // Trigger hero section animations immediately
                if (el.closest('#home')) {
                    setTimeout(() => {
                        el.classList.add('visible');
                    }, 100);
                } else {
                    observer.observe(el);
                }
            });

            // Active nav link on scroll
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('.nav-link');

            function updateActiveNavLink() {
                let current = '';
                const scrollPosition = window.pageYOffset + 150;

                sections.forEach(section => {
                    const sectionTop = section.offsetTop;
                    const sectionHeight = section.clientHeight;
                    if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                        current = section.getAttribute('id');
                    }
                });

                navLinks.forEach(link => {
                    link.classList.remove('nav-link--active');
                    if (link.getAttribute('href') === `#${current}`) {
                        link.classList.add('nav-link--active');
                    }
                });
            }

            window.addEventListener('scroll', updateActiveNavLink);
            updateActiveNavLink(); // Call once on load

            // Doctor Profile Modal Functions
            // Doctor data from database
            const doctorsData = <?php 
                $doctorsJson = [];
                foreach ($doctors as $doctor) {
                    $first_name = $doctor['first_name'] ?? 'Dr.';
                    $last_name = $doctor['last_name'] ?? '';
                    $full_name = trim($first_name . ' ' . $last_name);
                    if (empty($full_name) || $full_name === 'Dr.') {
                        $full_name = 'Dr. ' . ($doctor['specialty'] ?? 'Doctor');
                    } else {
                        $full_name = 'Dr. ' . $full_name;
                    }
                    
                    $doctorsJson[$doctor['id']] = [
                        'name' => $full_name,
                        'specialization' => $doctor['specialty'] ?? 'Specialist',
                        'credentials' => $doctor['certifications'] ?? '',
                        'experience' => ($doctor['years_experience'] ?? 0) . '+ years',
                        'description' => $doctor['description'] ?? '',
                        'schedule' => $doctor['schedule'] ?? 'By Appointment',
                        'location' => $doctor['location'] ?? 'Main Clinic',
                        'image' => $doctor['image'] ?? 'assets/img/doctor-placeholder.png',
                        'education' => $doctor['education'] ?? '',
                        'services' => $doctor['services'] ?? '',
                        'consultation_fee' => number_format($doctor['consultation_fee'] ?? 0, 2)
                    ];
                }
                echo json_encode($doctorsJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            ?>;
            
            const appointmentModal = document.getElementById('appointment-modal');
            const selectedDoctorInfo = document.getElementById('selected-doctor-info');
            const selectedDoctorName = document.getElementById('selected-doctor-name');
            const selectedDoctorSpecialization = document.getElementById('selected-doctor-specialization');
            const doctorField = document.querySelector('#appointment-modal input[name="doctor_id"]');
            const openDoctorSuggestionsBtn = document.getElementById('open-doctor-suggestions');
            const doctorSuggestions = document.getElementById('doctor-suggestions');
            const doctorSuggestionsGrid = document.getElementById('doctor-suggestions-grid');
            const fullNameInput = document.getElementById('appt-fullname');
            const hiddenFirstName = document.getElementById('appt-firstname-hidden');
            const hiddenLastName = document.getElementById('appt-lastname-hidden');
            const slotSelect = document.getElementById('appt-slot');
            const appointmentDateField = document.getElementById('appt-date');
            const appointmentTimeHidden = document.getElementById('appt-time-hidden');
            const appointmentTypeRadios = document.querySelectorAll('input[name="visit_type"]');
            const telehealthWrapper = document.getElementById('telehealth-wrapper');
            const telehealthPlatform = document.getElementById('telehealth-platform');
            const appointmentForm = document.getElementById('appointment-form');
            const appointmentFeedback = document.getElementById('appointment-feedback');
            const appointmentSubmitBtn = appointmentForm ? appointmentForm.querySelector('button[type="submit"]') : null;
            const contactInputField = document.getElementById('appt-contact');
            let availabilityAbortController = null;

            function syncHiddenNameFields(fullName) {
                if (!hiddenFirstName || !hiddenLastName) return;
                const trimmed = fullName.trim();
                if (!trimmed) {
                    hiddenFirstName.value = '';
                    hiddenLastName.value = '';
                    return;
                }
                const parts = trimmed.split(/\s+/);
                hiddenFirstName.value = parts.shift() || '';
                hiddenLastName.value = parts.join(' ');
            }

            if (fullNameInput) {
                syncHiddenNameFields(fullNameInput.value);
                fullNameInput.addEventListener('input', (event) => {
                    syncHiddenNameFields(event.target.value);
                });
            }

            function toggleTelehealthFields() {
                if (!telehealthWrapper) return;
                const selectedType = document.querySelector('input[name="visit_type"]:checked');
                const isTeleconsult = selectedType && selectedType.value === 'teleconsultation_video';
                telehealthWrapper.classList.toggle('hidden', !isTeleconsult);
                if (!isTeleconsult && telehealthPlatform) {
                    telehealthPlatform.value = '';
                }
            }

            function resetSlotSelect(message = 'Select a doctor and date') {
                if (!slotSelect) return;
                slotSelect.innerHTML = `<option value="">${message}</option>`;
                if (appointmentTimeHidden) {
                    appointmentTimeHidden.value = '';
                }
            }

            async function loadAvailableSlots() {
                if (!slotSelect || !appointmentDateField) return;
                const selectedDate = appointmentDateField.value;
                const doctorId = doctorField ? doctorField.value : '';
                const selectedType = document.querySelector('input[name="visit_type"]:checked');
                const appointmentType = selectedType ? selectedType.value : '';

                if (!selectedDate || !doctorId) {
                    resetSlotSelect('Select a doctor and date');
                    return;
                }

                slotSelect.innerHTML = '<option value="">Loading available slots...</option>';

                if (availabilityAbortController) {
                    availabilityAbortController.abort();
                }

                availabilityAbortController = new AbortController();
                const params = new URLSearchParams({
                    doctor_id: doctorId,
                    date: selectedDate,
                    appointment_type: appointmentType
                });

                try {
                    const response = await fetch(`modules/appointments/availability.php?${params.toString()}`, {
                        signal: availabilityAbortController.signal,
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    if (!response.ok) {
                        throw new Error('Unable to fetch slots');
                    }

                    const slots = await response.json();
                    renderSlots(slots);
                } catch (error) {
                    renderSlots(null);
                } finally {
                    availabilityAbortController = null;
                }
            }

            function renderSlots(slots) {
                if (!slotSelect) return;
                if (appointmentTimeHidden) {
                    appointmentTimeHidden.value = '';
                }
                const normalizedSlots = Array.isArray(slots) && slots.length ? slots : [
                    { value: '08:00', label: '08:00 AM' },
                    { value: '09:30', label: '09:30 AM' },
                    { value: '11:00', label: '11:00 AM' },
                    { value: '13:30', label: '01:30 PM' },
                    { value: '15:00', label: '03:00 PM' }
                ];

                slotSelect.innerHTML = '<option value="">Select a time slot</option>' + normalizedSlots.map(slot => {
                    if (typeof slot === 'string') {
                        return `<option value="${slot}">${slot}</option>`;
                    }
                    return `<option value="${slot.value}">${slot.label}</option>`;
                }).join('');
            }

            toggleTelehealthFields();
            resetSlotSelect();

            window.openAppointmentModal = function() {
                if (!appointmentModal) return;
                appointmentModal.classList.remove('hidden');
                appointmentModal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                toggleTelehealthFields();
                loadAvailableSlots();
            };

            window.closeAppointmentModal = function() {
                if (!appointmentModal) return;
                appointmentModal.classList.add('hidden');
                appointmentModal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            };

            window.openDoctorProfile = function(doctorId) {
                const modal = document.getElementById('doctor-profile-modal');
                const content = document.getElementById('doctor-profile-content');
                
                const doctors = doctorsData;

                const doctor = doctors[doctorId];
                if (doctor) {
                    content.innerHTML = `
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="md:col-span-1">
                                <div class="h-64 bg-gradient-to-br from-teal-500 to-teal-700 rounded-lg overflow-hidden mb-4">
                                    <img src="${doctor.image}" alt="${doctor.name}" class="w-full h-full object-cover">
                                </div>
                                <button type="button" class="w-full px-4 py-2 bg-teal-500 text-white rounded-lg hover:bg-teal-600 transition inline-flex items-center justify-center" onclick="bookDoctor(${doctorId})">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="m9 16 2 2 4-4"></path></svg>
                                    Make Appointment
                                </button>
                            </div>
                            <div class="md:col-span-2">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">${doctor.name}</h3>
                                <p class="text-teal-600 font-semibold mb-1">${doctor.specialization}</p>
                                <p class="text-sm text-gray-500 mb-4">${doctor.credentials}</p>
                                <div class="space-y-4">
                                    <div>
                                        <h4 class="font-semibold text-gray-900 mb-2">About</h4>
                                        <p class="text-gray-600">${doctor.description}</p>
                                    </div>
                                    ${doctor.services ? `
                                    <div>
                                        <h4 class="font-semibold text-gray-900 mb-1">Services & Specializations</h4>
                                        <p class="text-gray-600">${doctor.services}</p>
                                    </div>
                                    ` : ''}
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <h4 class="font-semibold text-gray-900 mb-1">Experience</h4>
                                            <p class="text-gray-600">${doctor.experience}</p>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-900 mb-1">Consultation Fee</h4>
                                            <p class="text-gray-600">₱${doctor.consultation_fee}</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <h4 class="font-semibold text-gray-900 mb-1">Schedule</h4>
                                            <p class="text-gray-600">${doctor.schedule}</p>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-900 mb-1">Location</h4>
                                            <p class="text-gray-600">${doctor.location}</p>
                                        </div>
                                    </div>
                                    ${doctor.education ? `
                                    <div>
                                        <h4 class="font-semibold text-gray-900 mb-1">Education & Training</h4>
                                        <p class="text-gray-600 text-sm">${doctor.education}</p>
                                    </div>
                                    ` : ''}
                                </div>
                        </div>
                        </div>
                    `;
                    
                    // Show modal
                    modal.classList.remove('hidden');
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden'; // Prevent background scrolling
                }
            };

            // Close doctor profile modal function
            window.closeDoctorProfile = function() {
                const modal = document.getElementById('doctor-profile-modal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.setAttribute('aria-hidden', 'true');
                    document.body.style.overflow = ''; // Restore scrolling
                }
            };

            function applyDoctorSelection(doctorId) {
                const doctor = doctorsData[doctorId];
                if (!doctor || !doctorField) return;

                doctorField.value = doctorId;
                if (selectedDoctorInfo && selectedDoctorName && selectedDoctorSpecialization) {
                    selectedDoctorName.textContent = doctor.name;
                    selectedDoctorSpecialization.textContent = doctor.specialization;
                    selectedDoctorInfo.classList.remove('hidden');
                }
                if (doctorSuggestions) {
                    doctorSuggestions.classList.add('hidden');
                }
                loadAvailableSlots();
            }

            window.bookDoctor = function(doctorId) {
                applyDoctorSelection(doctorId);
                closeDoctorProfile();
                openAppointmentModal();
            };

            function renderDoctorSuggestions(excludeDoctorId = null) {
                if (!doctorSuggestions || !doctorSuggestionsGrid) return;

                const suggestions = Object.entries(doctorsData)
                    .filter(([id]) => id !== String(excludeDoctorId))
                    .slice(0, 3);

                if (!suggestions.length) {
                    doctorSuggestions.classList.add('hidden');
                    return;
                }

                doctorSuggestionsGrid.innerHTML = suggestions.map(([id, doctor]) => `
                    <button type="button" class="w-full border border-gray-200 rounded-lg p-3 text-left bg-white hover:border-teal-500 hover:bg-teal-50 transition" data-suggested-doctor="${id}">
                        <p class="text-sm font-semibold text-gray-900">${doctor.name}</p>
                        <p class="text-xs text-gray-600">${doctor.specialization}</p>
                        <p class="text-[11px] text-gray-500 mt-1">Schedule: ${doctor.schedule}</p>
                    </button>
                `).join('');

                doctorSuggestions.classList.remove('hidden');
            }

            if (openDoctorSuggestionsBtn) {
                openDoctorSuggestionsBtn.addEventListener('click', () => {
                    const currentDoctorId = doctorField ? doctorField.value : null;
                    renderDoctorSuggestions(currentDoctorId);
                    if (doctorSuggestions) {
                        doctorSuggestions.classList.remove('hidden');
                    }
                });
            }

            if (doctorSuggestionsGrid) {
                doctorSuggestionsGrid.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-suggested-doctor]');
                    if (!button) return;
                    const doctorId = button.getAttribute('data-suggested-doctor');
                    applyDoctorSelection(doctorId);
                });
            }

            if (appointmentDateField) {
                appointmentDateField.addEventListener('change', () => {
                    loadAvailableSlots();
                });
            }

            if (slotSelect && appointmentTimeHidden) {
                slotSelect.addEventListener('change', (event) => {
                    appointmentTimeHidden.value = event.target.value;
                });
            }

            if (appointmentTypeRadios) {
                appointmentTypeRadios.forEach(radio => {
                    radio.addEventListener('change', () => {
                        toggleTelehealthFields();
                        loadAvailableSlots();
                    });
                });
            }

            function updateAppointmentFeedback(type, message) {
                if (!appointmentFeedback) return;
                const baseClasses = 'text-sm rounded-lg px-4 py-3 border transition';
                const colorClasses = type === 'success'
                    ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
                    : 'bg-rose-50 border-rose-200 text-rose-800';
                appointmentFeedback.className = `${baseClasses} ${colorClasses}`;
                appointmentFeedback.textContent = message;
                appointmentFeedback.classList.remove('hidden');
            }

            function clearAppointmentFeedback() {
                if (!appointmentFeedback) return;
                appointmentFeedback.classList.add('hidden');
                appointmentFeedback.textContent = '';
            }

            function setSubmitLoading(isLoading) {
                if (!appointmentSubmitBtn) return;

                if (!appointmentSubmitBtn.dataset.defaultContent) {
                    appointmentSubmitBtn.dataset.defaultContent = appointmentSubmitBtn.innerHTML.trim();
                }

                appointmentSubmitBtn.disabled = isLoading;
                appointmentSubmitBtn.classList.toggle('opacity-70', isLoading);
                appointmentSubmitBtn.setAttribute('aria-busy', isLoading ? 'true' : 'false');

                if (isLoading) {
                    appointmentSubmitBtn.innerHTML = `
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        Processing...
                    `;
                } else if (appointmentSubmitBtn.dataset.defaultContent) {
                    appointmentSubmitBtn.innerHTML = appointmentSubmitBtn.dataset.defaultContent;
                }
            }

            if (appointmentForm) {
                let shouldAutoCloseAppointmentModal = false;
                appointmentForm.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    clearAppointmentFeedback();
                    shouldAutoCloseAppointmentModal = false;

                    if (!doctorField || !doctorField.value) {
                        updateAppointmentFeedback('error', 'Please select a doctor before booking.');
                        return;
                    }

                    if (!appointmentDateField || !appointmentDateField.value) {
                        updateAppointmentFeedback('error', 'Please choose a preferred date.');
                        return;
                    }

                    if (!slotSelect || !slotSelect.value) {
                        updateAppointmentFeedback('error', 'Please choose an available time slot.');
                        return;
                    }

                    if (appointmentTimeHidden && !appointmentTimeHidden.value) {
                        appointmentTimeHidden.value = slotSelect.value;
                    }

                    const formData = new FormData(appointmentForm);
                    const selectedVisitType = document.querySelector('input[name="visit_type"]:checked');
                    if (selectedVisitType) {
                        formData.set('visit_type', selectedVisitType.value);
                    }
                    if (appointmentTimeHidden) {
                        formData.set('appointment_time', appointmentTimeHidden.value);
                    }

                    const payload = Object.fromEntries(formData.entries());

                    setSubmitLoading(true);

                    try {
                        const response = await fetch('api/api_public_booking.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify(payload)
                        });

                        const result = await response.json();

                        if (!response.ok || !result.success) {
                            throw new Error(result.message || 'Unable to submit appointment right now.');
                        }

                        updateAppointmentFeedback('success', result.message || 'Appointment request received! Please check your email for confirmation.');

                        appointmentForm.reset();
                        syncHiddenNameFields('');
                        if (doctorField) {
                            doctorField.value = '';
                        }
                        if (selectedDoctorInfo) {
                            selectedDoctorInfo.classList.add('hidden');
                        }
                        if (doctorSuggestions) {
                            doctorSuggestions.classList.add('hidden');
                        }
                        resetSlotSelect();
                        toggleTelehealthFields();
                        if (contactInputField) {
                            contactInputField.value = '';
                        }
                        shouldAutoCloseAppointmentModal = true;
                    } catch (error) {
                        updateAppointmentFeedback('error', error.message || 'Something went wrong while saving your appointment. Please try again.');
                    } finally {
                        setSubmitLoading(false);
                        if (shouldAutoCloseAppointmentModal) {
                            setTimeout(() => {
                                if (typeof closeAppointmentModal === 'function') {
                                    closeAppointmentModal();
                                }
                            }, 600);
                        }
                    }
                });
            }


            // Close doctor profile modal when clicking outside
            const doctorModal = document.getElementById('doctor-profile-modal');
            if (doctorModal) {
                doctorModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        window.closeDoctorProfile();
                    }
                });
            }

            // Close modal on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('doctor-profile-modal');
                    if (modal && !modal.classList.contains('hidden')) {
                        window.closeDoctorProfile();
                    }
                }
            });
        });

        // Doctor Filter Functionality
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const doctorCards = document.querySelectorAll('.doctor-card');
    
    // Filter functionality
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            const filter = this.getAttribute('data-filter');
            
            // Update active button
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Filter cards
            doctorCards.forEach(card => {
                if (filter === 'all' || card.getAttribute('data-specialization') === filter) {
                    card.style.display = 'flex';
                    card.classList.add('fade-in-up');
                } else {
                    card.style.display = 'none';
                    card.classList.remove('fade-in-up');
                }
            });
        });
    });
    
    // Contact Number Formatting (09xx xxx xxxx) - Auto-prefix with 09
    const contactInput = document.getElementById('appt-contact');
    if (contactInput) {
        // Set initial value to "09" when field is focused (if empty)
        contactInput.addEventListener('focus', function(e) {
            const value = e.target.value.replace(/\D/g, '');
            if (value.length === 0) {
                e.target.value = '09';
            }
        });
        
        contactInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ''); // Remove all non-digits
            
            // If empty, set to "09"
            if (value.length === 0) {
                e.target.value = '09';
                return;
            }
            
            // Always ensure it starts with 09
            // If user types random numbers, automatically prefix with 09
            if (!value.startsWith('09')) {
                // If they typed something that doesn't start with 09, 
                // take their input and make it "09" + their digits (max 9 digits)
                let userDigits = value;
                if (userDigits.length > 9) {
                    userDigits = userDigits.substring(0, 9); // Limit to 9 digits after 09
                }
                value = '09' + userDigits;
            }
            
            // Limit to 11 digits total (09 + 9 more digits)
            if (value.length > 11) {
                value = value.substring(0, 11);
            }
            
            // Format the number
            formatContactNumber(e.target, value);
        });
        
        // Format function
        function formatContactNumber(input, digits = null) {
            if (digits === null) {
                digits = input.value.replace(/\D/g, '');
            }
            
            // Ensure it starts with 09
            if (!digits.startsWith('09')) {
                if (digits.length > 9) {
                    digits = digits.substring(0, 9);
                }
                digits = '09' + digits;
            }
            
            // Format as 09xx xxx xxxx
            if (digits.length <= 2) {
                input.value = digits; // Just "09"
            } else if (digits.length <= 4) {
                input.value = digits; // "09xx"
            } else if (digits.length <= 7) {
                input.value = digits.substring(0, 4) + ' ' + digits.substring(4); // "09xx xxx"
            } else {
                input.value = digits.substring(0, 4) + ' ' + digits.substring(4, 7) + ' ' + digits.substring(7); // "09xx xxx xxxx"
            }
        }
        
        contactInput.addEventListener('keypress', function(e) {
            // Only allow numbers
            const char = String.fromCharCode(e.which);
            if (!/[0-9]/.test(char)) {
                e.preventDefault();
            }
        });
        
        // Prevent backspace/delete from removing "09" prefix
        contactInput.addEventListener('keydown', function(e) {
            const value = e.target.value.replace(/\D/g, '');
            const cursorPos = e.target.selectionStart;
            
            // If trying to delete and we're at position that would remove "09"
            if ((e.key === 'Backspace' || e.key === 'Delete') && value === '09') {
                e.preventDefault();
            }
            
            // If backspace at position 2 (after "09"), prevent if it would remove the "9"
            if (e.key === 'Backspace' && cursorPos === 2 && value.startsWith('09') && value.length === 2) {
                e.preventDefault();
            }
        });
        
        // Validate on blur
        contactInput.addEventListener('blur', function(e) {
            const value = e.target.value.replace(/\D/g, '');
            if (value.length > 0 && value.length < 11) {
                e.target.setCustomValidity('Please enter a complete 11-digit mobile number (09xx xxx xxxx)');
            } else if (value.length === 11 && !value.startsWith('09')) {
                e.target.setCustomValidity('Mobile number must start with 09');
            } else {
                e.target.setCustomValidity('');
            }
        });
    }
});
    </script>
</body>
</html>


