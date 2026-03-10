<?php
/**
 * Indian States dropdown helper
 * Usage: echo india_states_select('state', $selected_value, 'form-control');
 */
function india_states_options($selected = '') {
    $states = [
        'Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Goa','Gujarat',
        'Haryana','Himachal Pradesh','Jharkhand','Karnataka','Kerala','Madhya Pradesh',
        'Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland','Odisha','Punjab',
        'Rajasthan','Sikkim','Tamil Nadu','Telangana','Tripura','Uttar Pradesh',
        'Uttarakhand','West Bengal',
        // UTs
        'Andaman and Nicobar Islands','Chandigarh','Dadra and Nagar Haveli and Daman and Diu',
        'Delhi','Jammu and Kashmir','Ladakh','Lakshadweep','Puducherry'
    ];
    $html = '<option value="">-- Select State --</option>';
    foreach ($states as $s) {
        $sel = (strtolower($selected) === strtolower($s)) ? 'selected' : '';
        $html .= "<option value=\"$s\" $sel>$s</option>";
    }
    return $html;
}

function india_states_select($name, $selected = '', $class = 'form-control', $id = '', $extra = '') {
    $id_attr = $id ? "id=\"$id\"" : "id=\"$name\"";
    return "<select name=\"$name\" $id_attr class=\"$class\" $extra>" . india_states_options($selected) . "</select>";
}
