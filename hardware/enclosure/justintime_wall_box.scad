$fn = 48;

// JustInTime wall enclosure - semi-precise front prototype.
// The facade is aligned with confirmed screen + RC522 dimensions.
// The rear volume stays generic on purpose until the final PCB arrives.

render_mode = "print"; // "print" or "assembled"
part = "all"; // "all", "shell", "lid"
show_labels = false;

clearance = 0.35;
wall = 2.4;
front_wall = 2.0;
rfid_front_wall = 1.1;
lid_thickness = 2.6;
lid_border_height = 6;
lid_border_wall = 2.4;
corner_radius = 8;
rear_open_margin = 8;
rear_open_depth = 3.2;
screw_diameter = 3.0;
screw_clearance = 3.4;
pillar_diameter = 9;
corner_screw_offset = 10;
anchor_overlap = 0.2;

screen_pcb_w = 44;
screen_pcb_h = 73;
screen_pcb_stack_t = 8;
screen_view_w = 44;
screen_view_h = 52;
screen_window_w = 40;
screen_window_h = screen_view_h + 1.0;

rc522_w = 39;
rc522_h = 60;
rc522_stack_t = 7;
rc522_hole_d = 3.3;
rc522_mount_hole_d = 2.8;
rc522_slot_len = 1.4;
rc522_top_hole_inset_x = 7;
rc522_top_hole_inset_y = 7;
rc522_bottom_hole_inset_x = 3;
rc522_bottom_hole_from_bottom = 15;

usb_opening_w = 16;
usb_opening_h = 10;
led_hole_d = 5.2;
led_spacing = 14;
buzzer_hole_d = 2.2;
buzzer_grid_cols = 5;
buzzer_grid_rows = 3;
buzzer_grid_pitch = 4;

esp32_mount_enabled = true;
esp32_board_w = 51;
esp32_board_h = 28;
esp32_rotated = true;
esp32_hole_edge_margin = 1;
esp32_mount_center_x = 39;
esp32_mount_center_y = 150;
esp32_mount_pillar_d = 7;
esp32_mount_hole_d = 2.5;
esp32_mount_height = 2;
esp32_usb_side_notch_w = 13;
esp32_usb_side_notch_h = 5;
esp32_usb_side_notch_x = esp32_mount_center_x;
esp32_usb_side_notch_bottom = 0.8;
esp32_usb_print_support_enabled = true;
esp32_usb_print_support_w = 1.2;

outer_w = 70;
outer_h = 186;
outer_d = 40;
inner_w = outer_w - wall * 2;
inner_h = outer_h - wall * 2;
inner_d = outer_d - front_wall - lid_thickness;

screen_top_margin = 14;
screen_gap_to_rc522 = 12;
screen_pcb_x = (outer_w - screen_pcb_w) / 2;
screen_pcb_y = outer_h - screen_top_margin - screen_pcb_h;
screen_center_y = screen_pcb_y + screen_pcb_h / 2;

rc522_pcb_x = (outer_w - rc522_w) / 2;
rc522_pcb_y = screen_pcb_y - screen_gap_to_rc522 - rc522_h;
rc522_center_y = rc522_pcb_y + rc522_h / 2;

led_center_y = 15;
buzzer_center_x = outer_w / 2;
buzzer_center_y = 23;

screen_support_h = 6;
screen_side_rail_w = 2.4;
screen_bottom_stop_h = 2.4;
screen_support_clear = 0.6;
support_joint_overlap = 0.2;

function rc522_hole_positions() = [
  [rc522_top_hole_inset_x, rc522_h - rc522_top_hole_inset_y],
  [rc522_w - rc522_top_hole_inset_x, rc522_h - rc522_top_hole_inset_y],
  [rc522_bottom_hole_inset_x, rc522_bottom_hole_from_bottom],
  [rc522_w - rc522_bottom_hole_inset_x, rc522_bottom_hole_from_bottom]
];

function esp32_size_x() = esp32_rotated ? esp32_board_h : esp32_board_w;
function esp32_size_y() = esp32_rotated ? esp32_board_w : esp32_board_h;

function esp32_mount_positions() = [
  [
    esp32_mount_center_x - (esp32_size_x() - 2 * (esp32_hole_edge_margin + esp32_mount_hole_d / 2)) / 2,
    esp32_mount_center_y - (esp32_size_y() - 2 * (esp32_hole_edge_margin + esp32_mount_hole_d / 2)) / 2
  ],
  [
    esp32_mount_center_x + (esp32_size_x() - 2 * (esp32_hole_edge_margin + esp32_mount_hole_d / 2)) / 2,
    esp32_mount_center_y - (esp32_size_y() - 2 * (esp32_hole_edge_margin + esp32_mount_hole_d / 2)) / 2
  ],
  [
    esp32_mount_center_x - (esp32_size_x() - 2 * (esp32_hole_edge_margin + esp32_mount_hole_d / 2)) / 2,
    esp32_mount_center_y + (esp32_size_y() - 2 * (esp32_hole_edge_margin + esp32_mount_hole_d / 2)) / 2
  ],
  [
    esp32_mount_center_x + (esp32_size_x() - 2 * (esp32_hole_edge_margin + esp32_mount_hole_d / 2)) / 2,
    esp32_mount_center_y + (esp32_size_y() - 2 * (esp32_hole_edge_margin + esp32_mount_hole_d / 2)) / 2
  ]
];

module shell_body() {
  difference() {
    rounded_box([outer_w, outer_h, outer_d], corner_radius);

    translate([wall, wall, front_wall])
      rounded_box([inner_w, inner_h, inner_d + 1], max(corner_radius - wall, 1));

    // Rear service opening so the internals can stay generic until the final PCB exists.
    translate([
      rear_open_margin,
      rear_open_margin,
      outer_d - rear_open_depth
    ])
      rounded_box([
        outer_w - rear_open_margin * 2,
        outer_h - rear_open_margin * 2,
        rear_open_depth + 0.2
      ], max(corner_radius - rear_open_margin + 1, 1));

    // Screen window
    translate([
      outer_w / 2 - screen_window_w / 2,
      screen_center_y - screen_window_h / 2,
      -0.01
    ])
      cube([screen_window_w, screen_window_h, front_wall + 0.2]);

    // RFID thin section from inside, keeping only rfid_front_wall at the front.
    translate([
      rc522_pcb_x - 3,
      rc522_pcb_y - 3,
      rfid_front_wall
    ])
      cube([rc522_w + 6, rc522_h + 6, front_wall - rfid_front_wall + 0.2]);

    // LED holes
    for (dx = [-led_spacing / 2, led_spacing / 2])
      translate([outer_w / 2 + dx, led_center_y, -0.01])
        cylinder(h = front_wall + 0.2, d = led_hole_d);

    // Buzzer vent
    for (ix = [0 : buzzer_grid_cols - 1])
      for (iy = [0 : buzzer_grid_rows - 1])
        translate([
          buzzer_center_x + (ix - (buzzer_grid_cols - 1) / 2) * buzzer_grid_pitch,
          buzzer_center_y + (iy - (buzzer_grid_rows - 1) / 2) * buzzer_grid_pitch,
          -0.01
        ])
          cylinder(h = front_wall + 0.2, d = buzzer_hole_d);

    // Generic USB opening on bottom face. Final PCB may require a later adjustment.
    translate([outer_w / 2, wall + 6, outer_d - lid_thickness - 7])
      rotate([90, 0, 0])
        cube([usb_opening_w, usb_opening_h, wall + 0.4], center = true);

    // Lid screw holes pass-through in shell lip area
    for (pos = screw_positions())
      translate([pos[0], pos[1], outer_d - lid_thickness - 10])
        cylinder(h = lid_thickness + 12, d = screw_diameter);
  }

  // Internal pillars for lid screws.
  for (pos = screw_positions())
    translate([pos[0], pos[1], front_wall - anchor_overlap])
      difference() {
        cylinder(h = inner_d - 3 + anchor_overlap, d = pillar_diameter);
        cylinder(h = inner_d - 2.8 + anchor_overlap, d = screw_diameter);
      }

  screen_supports();
  rc522_mounts();
  cable_guides();
}

module screen_supports() {
  translate([screen_pcb_x - screen_side_rail_w - screen_support_clear, screen_pcb_y, front_wall - anchor_overlap])
    cube([screen_side_rail_w, screen_pcb_h, screen_support_h + anchor_overlap]);

  translate([screen_pcb_x + screen_pcb_w + screen_support_clear, screen_pcb_y, front_wall - anchor_overlap])
    cube([screen_side_rail_w, screen_pcb_h, screen_support_h + anchor_overlap]);

  translate([
    screen_pcb_x - screen_support_clear - support_joint_overlap,
    screen_pcb_y - screen_bottom_stop_h,
    front_wall - anchor_overlap
  ])
    cube([
      screen_pcb_w + screen_support_clear * 2 + support_joint_overlap * 2,
      screen_bottom_stop_h + support_joint_overlap,
      screen_support_h + anchor_overlap
    ]);
}

module rc522_mounts() {
  for (pos = rc522_hole_positions())
    translate([rc522_pcb_x + pos[0], rc522_pcb_y + pos[1], rfid_front_wall - anchor_overlap])
      rc522_standoff();
}

module lid_panel() {
  union() {
    difference() {
      union() {
        translate([0, 0, outer_d - lid_thickness])
          rounded_box([outer_w, outer_h, lid_thickness], corner_radius);

        translate([0, 0, outer_d])
          difference() {
            rounded_box([outer_w, outer_h, lid_border_height], corner_radius);
            translate([lid_border_wall, lid_border_wall, -0.01])
              rounded_box([
                outer_w - lid_border_wall * 2,
                outer_h - lid_border_wall * 2,
                lid_border_height + 0.02
              ], max(corner_radius - lid_border_wall, 1));
          }
      }

      for (pos = screw_positions())
        translate([pos[0], pos[1], outer_d - lid_thickness - 0.01])
          cylinder(h = lid_thickness + 0.2, d = screw_clearance);

      if (esp32_mount_enabled)
        translate([
          esp32_usb_side_notch_x - esp32_usb_side_notch_w / 2,
          outer_h - lid_border_wall - 0.01,
          outer_d + esp32_usb_side_notch_bottom
        ])
          cube([
            esp32_usb_side_notch_w,
            lid_border_wall + 0.02,
            esp32_usb_side_notch_h
          ]);
    }

    if (esp32_mount_enabled)
      esp32_mounts();

    if (esp32_mount_enabled && esp32_usb_print_support_enabled)
      usb_notch_print_support();
  }
}

module esp32_mounts() {
  for (pos = esp32_mount_positions())
    translate([pos[0], pos[1], outer_d])
      difference() {
        cylinder(h = esp32_mount_height + anchor_overlap, d = esp32_mount_pillar_d);
        translate([0, 0, -0.01])
          cylinder(h = esp32_mount_height + anchor_overlap + 0.02, d = esp32_mount_hole_d);
      }
}

module usb_notch_print_support() {
  translate([
    esp32_usb_side_notch_x - esp32_usb_print_support_w / 2,
    outer_h - lid_border_wall,
    outer_d + esp32_usb_side_notch_bottom
  ])
    cube([
      esp32_usb_print_support_w,
      lid_border_wall,
      esp32_usb_side_notch_h
    ]);
}

module rc522_standoff() {
  difference() {
    cylinder(h = 5.2 + anchor_overlap, d = 8);
    translate([0, 0, -0.01])
      slot_hole(5.4 + anchor_overlap, rc522_mount_hole_d, rc522_slot_len);
  }
}

module slot_hole(h, d, slot_len) {
  hull() {
    translate([-slot_len / 2, 0, 0])
      cylinder(h = h, d = d);
    translate([slot_len / 2, 0, 0])
      cylinder(h = h, d = d);
  }
}

module cable_guides() {
  guide_w = 18;
  guide_h = 4;
  guide_t = 4;
  guide_base_z = rfid_front_wall - anchor_overlap;

  for (y_pos = [18, 34])
    translate([outer_w / 2 - guide_w / 2, y_pos - guide_h / 2, guide_base_z])
      difference() {
        cube([guide_w, guide_h, guide_t + anchor_overlap]);
        translate([5, (guide_h - 2.2) / 2, -0.01])
          cube([guide_w - 10, 2.2, guide_t + anchor_overlap + 0.02]);
      }
}

function screw_positions() = [
  [corner_screw_offset, corner_screw_offset],
  [outer_w - corner_screw_offset, corner_screw_offset],
  [corner_screw_offset, outer_h - corner_screw_offset],
  [outer_w - corner_screw_offset, outer_h - corner_screw_offset]
];

module rounded_box(size, radius) {
  x = size[0];
  y = size[1];
  z = size[2];
  hull() {
    for (ix = [radius, x - radius])
      for (iy = [radius, y - radius])
        translate([ix, iy, 0])
          cylinder(h = z, r = radius);
  }
}

module assembled_view() {
  color([0.12, 0.12, 0.14]) shell_body();
  color([0.08, 0.08, 0.09]) lid_panel();
}

module print_view() {
  color([0.12, 0.12, 0.14]) shell_body();
  translate([outer_w + 18, 0, 0])
    color([0.08, 0.08, 0.09])
      translate([0, 0, -(outer_d - lid_thickness)])
        lid_panel();
}

if (part == "shell") {
  shell_body();
} else if (part == "lid") {
  if (render_mode == "print") {
    translate([0, 0, -(outer_d - lid_thickness)])
      lid_panel();
  } else {
    lid_panel();
  }
} else {
  if (render_mode == "assembled") {
    assembled_view();
  } else {
    print_view();
  }
}

if (show_labels) {
  echo(str("Outer size: ", outer_w, " x ", outer_h, " x ", outer_d, " mm"));
  echo(str("Screen PCB: ", screen_pcb_w, " x ", screen_pcb_h, " mm"));
  echo(str("Screen window: ", screen_view_w, " x ", screen_view_h, " mm"));
  echo(str("RC522 PCB: ", rc522_w, " x ", rc522_h, " mm"));
  echo(str("Lid border: ", lid_border_height, " mm high, ", lid_border_wall, " mm wall"));
  echo(str("ESP32 board: ", esp32_board_w, " x ", esp32_board_h, " mm"));
  echo(str("ESP32 rotated: ", esp32_rotated));
  echo(str("ESP32 hole edge margin: ", esp32_hole_edge_margin, " mm"));
}
