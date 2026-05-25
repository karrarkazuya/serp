# -*- coding: utf-8 -*-
from odoo import http
from odoo.http import request
import json


class map_controller(http.Controller):
    
    @http.route('/jt_api/hr/area_view', auth='user')
    def area_map_view(self,  id=0, **kw):
        
        google_token = False
        """
        try:
            google_map_token = request.env['jtemployees.map.tokens'].search([('active', '=', True)], limit=1)
            if len(google_map_token) == 0:
                return "Map Requires API Key Token"
            google_token = google_map_token[0].token
        except:
            return "This feature requires a token in the JT Map"
        """
        
        record = request.env["jtemployees.location.areas"].search([('id', '=', id)], limit=1)
        
        try:
            map_json_data = json.loads(record.area_data)
        except:
            map_json_data = {
                "north": 0,
                "south": 0,
                "east": 0,
                "west": 0
            }
        return request.render("jtemployees.map_area_view", {
            'record_id': record.id,
            'token': google_token,
            'north': map_json_data["north"],
            'south': map_json_data["south"],
            'east':  map_json_data["east"],
            'west':  map_json_data["west"],
        })
        
    @http.route('/jt_api/hr/map_pin_view', auth='user')
    def pin_map_view(self,  latitude=0, longitude=0, **kw):
        
        return request.render("jtemployees.map_pin_view", {
            'latitude': latitude,
            'longitude': longitude
        })
    
    
    @http.route('/jt_api/hr/area_update', auth='user')
    def pin_map_submit(self, record_id=0, data="", **kw):
        records = request.env["jtemployees.location.areas"].search([('id', '=', record_id)], limit=1)
        records[0].write({
            "area_data": data
        })
        