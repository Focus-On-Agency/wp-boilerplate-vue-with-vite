<?php

namespace PluginClassName\Support\Notifications;

if (!defined('ABSPATH')) {
	exit;
}

const NS = PluginClassName_NAME_SPACE;

return [
    'reservation_confirmation' => [
        'booking.id' => [
			'label' => __('ID prenotazione', NS),
			'example' => '123',
		],
		'booking.date:d M Y' => [
			'label' => __('Data prenotazione', NS),
			'example' => '1 Gen 2024',
		],
		'booking.time_slot:H:i' => [
			'label' => __('Orario prenotazione', NS),
			'example' => '20:30',
		],
		'booking.guests' => [
			'label' => __('Numero ospiti', NS),
			'example' => '4',
		],

		'booking.location.name' => [
			'label' => __('Nome location', NS),
			'example' => 'Ristorante Principale',
		],
		'booking.location.address' => [
			'label' => __('Indirizzo location', NS),
			'example' => 'Via Roma 1, Milano',
		],
		'booking.location.phone' => [
			'label' => __('Telefono location', NS),
			'example' => '+39 02 1234567',
		],
		
		'booking.customer.name' => [
            'label' => __('Nome cliente', NS),
            'example' => 'Mario',
        ],
        'booking.customer.surname'=> [
            'label' => __('Cognome cliente', NS),
            'example' => 'Rossi',
        ],
		'booking.customer.email' => [
			'label' => __('Email cliente', NS),
			'example' => 'mario.rossi@example.com',
		]
		,
		'booking.customer.phone' => [
			'label' => __('Telefono cliente', NS),
			'example' => '+39 333 1234567',
		],
	],
	'reservation_edit' => [
        'booking.id' => [
			'label' => __('ID prenotazione', NS),
			'example' => '123',
		],
		'booking.date:d M Y' => [
			'label' => __('Data prenotazione', NS),
			'example' => '1 Gen 2024',
		],
		'booking.time_slot:H:i' => [
			'label' => __('Orario prenotazione', NS),
			'example' => '20:30',
		],
		'booking.guests' => [
			'label' => __('Numero ospiti', NS),
			'example' => '4',
		],

		'booking.location.name' => [
			'label' => __('Nome location', NS),
			'example' => 'Ristorante Principale',
		],
		'booking.location.address' => [
			'label' => __('Indirizzo location', NS),
			'example' => 'Via Roma 1, Milano',
		],
		'booking.location.phone' => [
			'label' => __('Telefono location', NS),
			'example' => '+39 02 1234567',
		],
		
		'booking.customer.name' => [
            'label' => __('Nome cliente', NS),
            'example' => 'Mario',
        ],
        'booking.customer.surname'=> [
            'label' => __('Cognome cliente', NS),
            'example' => 'Rossi',
        ],
		'booking.customer.email' => [
			'label' => __('Email cliente', NS),
			'example' => 'mario.rossi@example.com',
		]
		,
		'booking.customer.phone' => [
			'label' => __('Telefono cliente', NS),
			'example' => '+39 333 1234567',
		],
	],
	'reservation_delete' => [
		'booking.id' => [
			'label' => __('ID prenotazione', NS),
			'example' => '123',
		],
		'booking.date:d M Y' => [
			'label' => __('Data prenotazione', NS),
			'example' => '1 Gen 2024',
		],
		'booking.time_slot:H:i' => [
			'label' => __('Orario prenotazione', NS),
			'example' => '20:30',
		],
		'booking.guests' => [
			'label' => __('Numero ospiti', NS),
			'example' => '4',
		],

		'booking.location.name' => [
			'label' => __('Nome location', NS),
			'example' => 'Ristorante Principale',
		],
		'booking.location.address' => [
			'label' => __('Indirizzo location', NS),
			'example' => 'Via Roma 1, Milano',
		],
		'booking.location.phone' => [
			'label' => __('Telefono location', NS),
			'example' => '+39 02 1234567',
		],
		
		'booking.customer.name' => [
			'label' => __('Nome cliente', NS),
			'example' => 'Mario',
		],
		'booking.customer.surname'=> [
			'label' => __('Cognome cliente', NS),
			'example' => 'Rossi',
		],
		'booking.customer.email' => [
			'label' => __('Email cliente', NS),
			'example' => 'mario.rossi@example.com',
		],
		'booking.customer.phone' => [
			'label' => __('Telefono cliente', NS),
			'example' => '+39 333 1234567',
		],
	],
	'reservation_reminder' => [
		'booking.id' => [
			'label' => __('ID prenotazione', NS),
			'example' => '123',
		],
		'booking.date:d M Y' => [
			'label' => __('Data prenotazione', NS),
			'example' => '1 Gen 2024',
		],
		'booking.time_slot:H:i' => [
			'label' => __('Orario prenotazione', NS),
			'example' => '20:30',
		],
		'booking.guests' => [
			'label' => __('Numero ospiti', NS),
			'example' => '4',
		],

		'booking.location.name' => [
			'label' => __('Nome location', NS),
			'example' => 'Ristorante Principale',
		],
		'booking.location.address' => [
			'label' => __('Indirizzo location', NS),
			'example' => 'Via Roma 1, Milano',
		],
		'booking.location.phone' => [
			'label' => __('Telefono location', NS),
			'example' => '+39 02 1234567',
		],
		
		'booking.customer.name' => [
			'label' => __('Nome cliente', NS),
			'example' => 'Mario',
		],
		'booking.customer.surname'=> [
			'label' => __('Cognome cliente', NS),
			'example' => 'Rossi',
		],
		'booking.customer.email' => [
			'label' => __('Email cliente', NS),
			'example' => 'mario.rossi@example.com',
		],
		'booking.customer.phone' => [
			'label' => __('Telefono cliente', NS),
			'example' => '+39 333 1234567',
		],
	],
	'takeaway_new_order' => [
		'booking.id' => [
			'label' => __('ID ordine', NS),
			'example' => '456',
		],
		'booking.date:d M Y' => [
			'label' => __('Data ordine', NS),
			'example' => '1 Gen 2024',
		],
		'booking.time_slot:H:i' => [
			'label' => __('Orario ordine', NS),
			'example' => '19:00',
		],
		'booking.total_price_cents' => [
			'label' => __('Prezzo totale', NS),
			'example' => '45.00',
		],
		'booking.items' => [
			'label' => __('Dettagli articoli', NS),
			'example' => "1x Pizza Margherita - 10.00\n2x Coca Cola - 5.00",
		],
		'booking.status' => [
			'label' => __('Stato ordine', NS),
			'example' => 'In preparazione',
		],
		
		'booking.customer.name' => [
			'label' => __('Nome cliente', NS),
			'example' => 'Luigi',
		],
		'booking.customer.surname'=> [
			'label' => __('Cognome cliente', NS),
			'example' => 'Verdi',
		],
		'booking.customer.email' => [
			'label' => __('Email cliente', NS),
			'example' => 'luigi.verdi@example.com',
		],
		'booking.customer.phone' => [
			'label' => __('Telefono cliente', NS),
			'example' => '+39 333 7654321',
		],

		'booking.location.name' => [
			'label' => __('Nome location', NS),
			'example' => 'Ristorante Principale',
		],
		'booking.location.address' => [
			'label' => __('Indirizzo location', NS),
			'example' => 'Via Roma 1, Milano',
		],
		'booking.location.phone' => [
			'label' => __('Telefono location', NS),
			'example' => '+39 02 1234567',
		],
	],
	'takeaway_order_status_change' => [
		'booking.id' => [
			'label' => __('ID ordine', NS),
			'example' => '456',
		],
		'booking.date:d M Y' => [
			'label' => __('Data ordine', NS),
			'example' => '1 Gen 2024',
		],
		'booking.time_slot:H:i' => [
			'label' => __('Orario ordine', NS),
			'example' => '19:00',
		],
		'booking.total_price_cents' => [
			'label' => __('Prezzo totale', NS),
			'example' => '45.00',
		],
		'booking.items' => [
			'label' => __('Dettagli articoli', NS),
			'example' => "1x Pizza Margherita - 10.00\n2x Coca Cola - 5.00",
		],
		'booking.status' => [
			'label' => __('Stato ordine', NS),
			'example' => 'In preparazione',
		],
		
		'booking.customer.name' => [
			'label' => __('Nome cliente', NS),
			'example' => 'Luigi',
		],
		'booking.customer.surname'=> [
			'label' => __('Cognome cliente', NS),
			'example' => 'Verdi',
		],
		'booking.customer.email' => [
			'label' => __('Email cliente', NS),
			'example' => 'luigi.verdi@example.com',
		],
		'booking.customer.phone' => [
			'label' => __('Telefono cliente', NS),
			'example' => '+39 333 7654321',
		],

		'booking.location.name' => [
			'label' => __('Nome location', NS),
			'example' => 'Ristorante Principale',
		],
		'booking.location.address' => [
			'label' => __('Indirizzo location', NS),
			'example' => 'Via Roma 1, Milano',
		],
		'booking.location.phone' => [
			'label' => __('Telefono location', NS),
			'example' => '+39 02 1234567',
		],
	],
	'takeaway_reminder' => [
		'booking.id' => [
			'label' => __('ID ordine', NS),
			'example' => '456',
		],
		'booking.date:d M Y' => [
			'label' => __('Data ordine', NS),
			'example' => '1 Gen 2024',
		],
		'booking.time_slot:H:i' => [
			'label' => __('Orario ordine', NS),
			'example' => '19:00',
		],
		'booking.total_price_cents' => [
			'label' => __('Prezzo totale', NS),
			'example' => '45.00',
		],
		'booking.items' => [
			'label' => __('Dettagli articoli', NS),
			'example' => "1x Pizza Margherita - 10.00\n2x Coca Cola - 5.00",
		],
		'booking.status' => [
			'label' => __('Stato ordine', NS),
			'example' => 'In preparazione',
		],
		
		'booking.customer.name' => [
			'label' => __('Nome cliente', NS),
			'example' => 'Luigi',
		],
		'booking.customer.surname'=> [
			'label' => __('Cognome cliente', NS),
			'example' => 'Verdi',
		],
		'booking.customer.email' => [
			'label' => __('Email cliente', NS),
			'example' => 'luigi.verdi@example.com',
		],
		'booking.customer.phone' => [
			'label' => __('Telefono cliente', NS),
			'example' => '+39 333 7654321',
		],

		'booking.location.name' => [
			'label' => __('Nome location', NS),
			'example' => 'Ristorante Principale',
		],
		'booking.location.address' => [
			'label' => __('Indirizzo location', NS),
			'example' => 'Via Roma 1, Milano',
		],
		'booking.location.phone' => [
			'label' => __('Telefono location', NS),
			'example' => '+39 02 1234567',
		],
	],
	'review_request_1' => [
		'booking.id' => [
			'label' => __('ID prenotazione/ordine', NS),
			'example' => '123/456',
		],
		'booking.date:d M Y' => [
			'label' => __('Data prenotazione/ordine', NS),
			'example' => '1 Gen 2024',
		],
		'booking.time_slot:H:i' => [
			'label' => __('Orario prenotazione/ordine', NS),
			'example' => '20:30 / 19:00',
		],
		
		'booking.location.name' => [
			'label' => __('Nome location', NS),
			'example' => 'Ristorante Principale',
		],
		'booking.location.address' => [
			'label' => __('Indirizzo location', NS),
			'example' => 'Via Roma 1, Milano',
		],
		'booking.location.phone' => [
			'label' => __('Telefono location', NS),
			'example' => '+39 02 1234567',
		],
		
		'booking.customer.name' => [
			'label' => __('Nome cliente', NS),
			'example' => 'Mario/Luigi',
		],
		'booking.customer.surname'=> [
			'label' => __('Cognome cliente', NS),
			'example' => 'Rossi/Verdi',
		],
	],
	'admin_new_reservation' => [
		'booking.id' => [
			'label' => __('ID prenotazione', NS),
			'example' => '123',
		],
		'booking.date:d M Y' => [
			'label' => __('Data prenotazione', NS),
			'example' => '1 Gen 2024',
		],
		'booking.time_slot:H:i' => [
			'label' => __('Orario prenotazione', NS),
			'example' => '20:30',
		],
		'booking.guests' => [
			'label' => __('Numero ospiti', NS),
			'example' => '4',
		],

		'booking.location.name' => [
			'label' => __('Nome location', NS),
			'example' => 'Ristorante Principale',
		],
		'booking.location.address' => [
			'label' => __('Indirizzo location', NS),
			'example' => 'Via Roma 1, Milano',
		],
		'booking.location.phone' => [
			'label' => __('Telefono location', NS),
			'example' => '+39 02 1234567',
		],
		
		'booking.customer.name' => [
			'label' => __('Nome cliente', NS),
			'example' => 'Mario',
		],
		'booking.customer.surname'=> [
			'label' => __('Cognome cliente', NS),
			'example' => 'Rossi',
		],
		'booking.customer.email' => [
			'label' => __('Email cliente', NS),
			'example' => 'mario.rossi@example.com',
		],
		'booking.customer.phone' => [
			'label' => __('Telefono cliente', NS),
			'example' => '+39 333 1234567',
		],
	],
	'admin_new_takeaway_order' => [
		'booking.detailsFormatted' => [
			'label' => __('Dettagli articoli', NS),
			'example' => "1x Pizza Margherita - 10.00\n2x Coca Cola - 5.00",
		],
		'booking.id' => [
			'label' => __('ID ordine', NS),
			'example' => '456',
		],
		'booking.date:d M Y' => [
			'label' => __('Data ordine', NS),
			'example' => '1 Gen 2024',
		],
		'booking.time_slot:H:i' => [
			'label' => __('Orario ordine', NS),
			'example' => '19:00',
		],
		'booking.total' => [
			'label' => __('Prezzo totale', NS),
			'example' => '45,00',
		],
		'booking.payment_method' => [
			'label' => __('Metodo di pagamento', NS),
			'example' => 'Carta di credito',
		],
		
		'booking.status' => [
			'label' => __('Stato ordine', NS),
			'example' => 'In preparazione',
		],
		
		'booking.customer.name' => [
			'label' => __('Nome cliente', NS),
			'example' => 'Luigi',
		],
		'booking.customer.surname'=> [
			'label' => __('Cognome cliente', NS),
			'example' => 'Verdi',
		],
		'booking.customer.email' => [
			'label' => __('Email cliente', NS),
			'example' => 'luigi.verdi@example.com',
		],
		'booking.customer.phone' => [
			'label' => __('Telefono cliente', NS),
			'example' => '+39 333 7654321',
		],
		'booking.location.name' => [
			'label' => __('Nome location', NS),
			'example' => 'Ristorante Principale',
		],
		'booking.location.address' => [
			'label' => __('Indirizzo location', NS),
			'example' => 'Via Roma 1, Milano',
		],
		'booking.location.phone' => [
			'label' => __('Telefono location', NS),
			'example' => '+39 02 1234567',
		],
	],
];