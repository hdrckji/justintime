$fn = 48;

// JustInTime wall enclosure prototype
// Standard-assumption version for ESP32 + ILI9341 + RC522.

render_mode = "print"; // "print" or "assembled"
part = "all"; // "all", "shell", "lid"
show_labels = false;

clearance = 0.35;
wall = 2.4;
front_wall = 2.0;
rfid_front_wall = 1.2;
lid_thickness = 2.6;
corner_radius = 8;
rear_open_margin = 10;
rear_open_depth = 3.2;
screw_diameter = 3.0;
screw_clearance = 3.4;
pillar_diameter = 9;
pillar_wall = 2.2;

screen_pcb_w = 86;
screen_pcb_h = 50;
screen_pcb_t = 2;
screen_view_w = 58;
screen_view_h = 44;
screen_hole_spacing_x = 78;
screen_hole_spacing_y = 42;
screen_hole_d = 3.2;

rc522_w = 60;
rc522_h = 40;
rc522_t = 1.7;
rc522_hole_spacing_x = 53;
rc522_hole_spacing_y = 33;
rc522_hole_d = 3.2;

esp32_w = 28;
esp32_h = 55;
esp32_t = 14;
esp32_usb_w = 13;
esp32_usb_h = 8;

led_hole_d = 5.2;
led_spacing = 14;
buzzer_hole_d = 2.2;
buzzer_grid_cols = 5;
buzzer_grid_rows = 3;
buzzer_grid_pitch = 4;

inner_w = 102;
inner_h = 160;
inner_d = 29;
outer_w = inner_w + wall * 2;
outer_h = inner_h + wall * 2;
outer_d = inner_d + front_wall + lid_thickness;

screen_center_y = outer_h - 42;
rc522_center_y = 62;
led_center_y = 24;
buzzer_center_x = outer_w / 2;
buzzer_center_y = 18;

module shell_body() {
  difference() {
    rounded_box([outer_w, outer_h, outer_d], corner_radius);

    translate([wall, wall, front_wall])
      rounded_box([inner_w, inner_h, inner_d + 1], max(corner_radius - wall, 1));

    // Rear service opening so electronics can be mounted from the back.
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
      outer_w / 2 - (screen_view_w + 2) / 2,
      screen_center_y - (screen_view_h + 2) / 2,
      -0.01
    ])
      cube([screen_view_w + 2, screen_view_h + 2, front_wall + 0.2]);

    // RFID thin section from inside, keeping only rfid_front_wall at the front
    translate([
      (outer_w - (rc522_w + 8)) / 2,
      rc522_center_y - (rc522_h + 8) / 2,
      rfid_front_wall
    ])
      cube([rc522_w + 8, rc522_h + 8, front_wall - rfid_front_wall + 0.2]);

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

    // USB opening on bottom face
    translate([outer_w / 2, wall + 6, outer_d - lid_thickness - 7])
      rotate([90, 0, 0])
        cube([esp32_usb_w + 3, esp32_usb_h + 2, wall + 0.4], center = true);

    // Lid screw holes pass-through in shell lip area
    for (pos = screw_positions())
      translate([pos[0], pos[1], outer_d - lid_thickness - 10])
        cylinder(h = lid_thickness + 12, d = screw_diameter);
  }

  // Internal pillars for lid screws
  for (pos = screw_positions())
    translate([pos[0], pos[1], front_wall + 3])
      difference() {
        cylinder(h = inner_d - 6, d = pillar_diameter);
        cylinder(h = inner_d - 5.8, d = screw_diameter);
      }

  // Screen standoffs
  for (sx = [-screen_hole_spacing_x / 2, screen_hole_spacing_x / 2])
    for (sy = [-screen_hole_spacing_y / 2, screen_hole_spacing_y / 2])
      translate([outer_w / 2 + sx, screen_center_y + sy, front_wall])
        screen_standoff();

  // RC522 standoffs under the screen
  for (sx = [-rc522_hole_spacing_x / 2, rc522_hole_spacing_x / 2])
    for (sy = [-rc522_hole_spacing_y / 2, rc522_hole_spacing_y / 2])
      translate([outer_w / 2 + sx, rc522_center_y + sy, rfid_front_wall])
        rc522_standoff();

  // ESP32 tray and strap slots
  esp32_tray();
}

module lid_panel() {
  difference() {
    translate([0, 0, outer_d - lid_thickness])
      rounded_box([outer_w, outer_h, lid_thickness], corner_radius);

    for (pos = screw_positions())
      translate([pos[0], pos[1], outer_d - lid_thickness - 0.01])
        cylinder(h = lid_thickness + 0.2, d = screw_clearance);
  }
}

module screen_standoff() {
  difference() {
    cylinder(h = 6, d = 7);
    translate([0, 0, -0.01])
      cylinder(h = 6.2, d = 2.6);
  }
}

module rc522_standoff() {
  difference() {
    cylinder(h = 5, d = 7);
    translate([0, 0, -0.01])
      cylinder(h = 5.2, d = 2.6);
  }
}

module esp32_tray() {
  tray_w = esp32_w + 10;
  tray_h = esp32_h + 10;
  tray_t = 2.2;
  tray_y = 26;
  tray_z = front_wall + 10;

  translate([outer_w / 2 - tray_w / 2, tray_y, tray_z])
    cube([tray_w, tray_h, tray_t]);

  // Side rails
  translate([outer_w / 2 - tray_w / 2, tray_y, tray_z + tray_t])
    cube([2.2, tray_h, 7]);
  translate([outer_w / 2 + tray_w / 2 - 2.2, tray_y, tray_z + tray_t])
    cube([2.2, tray_h, 7]);

  // End stop
  translate([outer_w / 2 - tray_w / 2, tray_y + tray_h - 2.2, tray_z + tray_t])
    cube([tray_w, 2.2, 5]);

  // Zip tie tabs
  for (offset = [12, tray_h - 12])
    translate([outer_w / 2, tray_y + offset, tray_z + tray_t + 3.5])
      difference() {
        cube([tray_w - 6, 4, 4], center = true);
        cube([tray_w - 18, 2.2, 5], center = true);
      }
}

function screw_positions() = [
  [14, 14],
  [outer_w - 14, 14],
  [14, outer_h - 14],
  [outer_w - 14, outer_h - 14]
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
  echo(str("Screen PCB assumption: ", screen_pcb_w, " x ", screen_pcb_h, " mm"));
  echo(str("RC522 assumption: ", rc522_w, " x ", rc522_h, " mm"));
}
