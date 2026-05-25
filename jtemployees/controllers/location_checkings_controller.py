# -*- coding: utf-8 -*-
from collections import OrderedDict
import base64
from odoo import http, fields
from odoo.exceptions import ValidationError, UserError

from odoo.osv import expression
from odoo.addons.portal.controllers.portal import CustomerPortal, pager as portal_pager
from odoo.http import request
from werkzeug.utils import redirect
from datetime import datetime, timedelta
from zoneinfo import ZoneInfo
from pytz import timezone
import json
import math

class location_checkings_controller(http.Controller):

            
    @http.route('/jt_api/hr/location_checkin/type', type='http', auth='none', methods=['GET'], csrf=False)
    def location_checkin_type(self, **kw):
        
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            employee_id = False
            try:
                employee_id  = kw.get('employee_id')
            except:
                pass
            
            main_employee    = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            if employee_id:
                childs = self.get_all_childs(request=request, employee_id=main_employee.id)
                if int(employee_id) not in childs:
                    return self.response_json({'status': 'error', "code": 16, 'message': 'You have no permission to change this request'})
                employee    = request.env['hr.employee'].sudo().search([('id', '=', employee_id)], limit=1)
            else:
                employee    = main_employee
            
            employee_id = employee.id
            
   
            last_checking = request.env['jtemployees.fd.log'].sudo().search([
                (
                    "employee_id", "=", employee_id
                )
            ], order='id desc',limit=1)
            
            if not last_checking:
                response = {
                    'status': 'success',
                    'data': {
                        "employee_id": employee_id,
                        "check_in": True,
                        "check_out": False
                    }
                }
            else:
                
                was_created_within_1_hour = (
                    last_checking.create_date >= fields.Datetime.now() - timedelta(hours=1)
                )
                was_created_within_20_hour = (
                    last_checking.create_date >= fields.Datetime.now() - timedelta(hours=20)
                )
                    
                if was_created_within_1_hour:
                    response = {
                        'status': 'success',
                        'data': {
                            "employee_id": employee_id,
                            "check_in": False,
                            "check_out": False
                        }
                    }
                    return self.response_json(response)
                        
                if last_checking.check_in and not last_checking.check_out and was_created_within_20_hour:
                    response = {
                        'status': 'success',
                        'data': {
                            "employee_id": employee_id,
                            "check_in": False,
                            "check_out": True
                        }
                    }
                elif last_checking.check_in and not last_checking.check_out and not was_created_within_20_hour:
                    response = {
                        'status': 'success',
                        'data': {
                            "employee_id": employee_id,
                            "check_in": True,
                            "check_out": False
                        }
                    }
                elif last_checking.check_in and last_checking.check_out:
                    response = {
                        'status': 'success',
                        'data': {
                            "employee_id": employee_id,
                            "check_in": True,
                            "check_out": False
                        }
                    }
                else:
                    response = {
                        'status': 'success',
                        'data': {
                            "employee_id": employee_id,
                            "check_in": True,
                            "check_out": False
                        }
                    }
                
            return self.response_json(response)
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'code':4,
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
        
    @http.route('/jt_api/hr/location_checkin/submit', type='http', auth='none', methods=['POST'], csrf=False)
    def location_checkin_submit(self, **kw):
        
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            latitude               = float(kw.get('latitude'))
            longitude              = float(kw.get('longitude'))
            extra_gps_details      = kw.get('extra_gps_details')
            type                   = kw.get('type')
            image                  = request.httprequest.files.get('image')
            
            employee_id = False
            try:
                employee_id        = kw.get('employee_id')
            except:
                pass
            
            main_employee    = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            if employee_id:
                childs = self.get_all_childs(request=request, employee_id=main_employee.id)
                if int(employee_id) not in childs:
                    return self.response_json({'status': 'error', "code": 16, 'message': 'You have no permission to change this request'})
                employee    = request.env['hr.employee'].sudo().search([('id', '=', employee_id)], limit=1)
            else:
                employee    = main_employee
                
            employee_id = employee.id
            
            if not image:
                return self.response_json({'status': 'error', "code": 24, 'message': 'missing image'})
            image = base64.b64encode(image.read())
            
            if type == "check_in":
                employee_location_area = employee.jt_location_area
            else:
                employee_location_area = employee.jt_location_area_checkout
                if not employee_location_area:
                    employee_location_area = employee.jt_location_area
            
            if not employee_location_area.check_inside_area(latitude=latitude, longitude=longitude):
                return self.response_json({'status': 'error', "code": 42, 'message': 'Out of area'})
                
            # because odoo saves from local timezone into UTC however. when it displays. it displays as local time
            local_tz = timezone('Asia/Baghdad')
            current_date_time = datetime.now()
            current_date_time = local_tz.localize(current_date_time).replace(tzinfo=None)
                    
            current_day = datetime.now(timezone("Asia/Baghdad")).strftime('%Y-%m-%d')
            
        
            if type == "check_in":
                
                # we delete old attendances
                attendance_un_logged = request.env['hr.attendance'].sudo().search([
                    ('employee_id', '=', employee_id),
                    ('check_out', '=', False),
                ])
                
                for item in attendance_un_logged:
                    item.check_out = item.check_in
                    
                checkin_record_today = request.env['jtemployees.fd.log'].sudo().check_in_out_employee(employee.jt_fingerprint_id, current_date_time, "gps")
                
                if not checkin_record_today:
                    return self.response_json({'status': 'error', "code": 43, 'message': 'Checkin already made'})
                
                checkin_record_today.checkin_fingerprint = "GPS: " + employee_location_area.name
                checkin_record_today.user_id = employee.jt_fingerprint_id
                checkin_record_today.day = current_day
                checkin_record_today.location_checked_in = employee_location_area.id
                checkin_record_today.image = image
                checkin_record_today.latitude = latitude
                checkin_record_today.longitude = longitude
                checkin_record_today.ip_address = http.request.httprequest.remote_addr
                checkin_record_today.extra_gps_details = extra_gps_details
                checkin_record_today.attendance_id.in_latitude = latitude
                checkin_record_today.attendance_id.in_longitude = longitude
                    
            elif type == "check_out":
                checkin_record_today = request.env['jtemployees.fd.log'].sudo().check_in_out_employee(employee.jt_fingerprint_id, current_date_time, "gps")
                
                if not checkin_record_today:
                    return self.response_json({'status': 'error', "code": 43, 'message': 'Check out not possible'})
                
                
                checkin_record_today.checkout_fingerprint = "GPS: " + employee_location_area.name
                checkin_record_today.attendance_id.out_latitude = latitude
                checkin_record_today.attendance_id.out_longitude = longitude
                checkin_record_today.location_checked_out = employee_location_area.id
                checkin_record_today.image_checkout = image
                checkin_record_today.in_and_out = True
                checkin_record_today.latitude_checkout = latitude
                checkin_record_today.longitude_checkout = longitude
            else:
                return self.response_json({'status': 'error', "code": 4, 'message': 'Bad inputs'})
            
            response = {
                'status': 'success',
                'data': checkin_record_today.id
            }
            
            return self.response_json(response)
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'code':4,
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
        
    @http.route('/jt_api/hr/location_checkin/locations', type='http', auth='none', methods=['GET'], csrf=False)
    def location_checkin_locations(self, **kw):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            page = kw.get('page')
            
            if not page:
                page = 1
            else:
                page = int(page)
                
            employee = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)

            page_size = 100
            start = (page - 1) * page_size
            end = start + page_size
            
            requests = request.env['jtemployees.location.areas'].sudo().search([( "employee_id", "=", employee.id), ("deleted", "=", False)], 
                                                                        limit=page_size, 
                                                                        offset=start, 
                                                                        order='id desc')
            
            requests_count = request.env['jtemployees.location.areas'].sudo().search_count([( "employee_id", "=", employee.id), ("deleted", "=", False)])
            last_page = math.ceil(requests_count / page_size)
            
            results = []
            
            childs = self.get_all_childs(request=request, employee_id=employee.id)
            
            for item in requests:
                sub_employees = []
                employees = request.env['hr.employee'].sudo().search([('jt_has_location_checkin', '=', True) , ('jt_location_area', '=', item.id)])
                for sub_employee in employees:
                    if sub_employee.id in childs:
                        sub_employees.append({
                            "id": sub_employee.id,
                            "title": sub_employee.name
                        })
                        
                sub_employees_checkout = []
                employees = request.env['hr.employee'].sudo().search([('jt_has_location_checkin', '=', True) , ('jt_location_area_checkout', '=', item.id)])
                for sub_employee in employees:
                    if sub_employee.id in childs:
                        sub_employees_checkout.append({
                            "id": sub_employee.id,
                            "title": sub_employee.name
                        })
                        
                if not item.area_data:
                    continue
                item.area_data = item.area_data.replace('\n', '').replace('\\', '').replace(' ', '').replace('\'', '"')
                results.append({
                    "id": item.id,
                    "title": item.name,
                    "area_data": item.area_data,
                    "assigned_employees": sub_employees,
                    "assigned_employees_checkout": sub_employees_checkout,
                    "created_at": item.create_date
                })
            response = {
                'status': 'success',
                'data': results,
                'current_page': page,
                'last_page': last_page
            }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
      
    @http.route('/jt_api/hr/location_checkin/locations/create', type='http', auth='none', methods=['POST'], csrf=False)
    def location_checkin_locations_create(self, **kw):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            employee    = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            employee_id = employee.id
            
            title = kw.get('title')
            area_data = kw.get('area_data')
            
            area_data = self.make_square(area_data)
            
            record = request.env['jtemployees.location.areas'].sudo().create({
                "employee_id": employee_id,
                "name": title,
                "area_data": area_data
            })
         
            response = {
                'status': 'success',
                'data': record.id
            }
            
            return self.response_json(response)
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'code':4,
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
        
    @http.route('/jt_api/hr/location_checkin/locations/update', type='http', auth='none', methods=['POST'], csrf=False)
    def location_checkin_locations_update(self, **kw):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            employee    = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            employee_id = employee.id
            
            item_id   = kw.get('item_id')
            title     = kw.get('title')
            area_data = kw.get('area_data')
            
            area_data = self.make_square(area_data)
            
            record = request.env['jtemployees.location.areas'].sudo().search([
                (
                    "id", "=", item_id
                ),
                (
                    "employee_id", "=", employee_id
                )
            ], limit=1)
            
            if record:
                record.name      = title
                record.area_data = area_data
            
            response = {
                'status': 'success',
                'data': item_id
            }
            return self.response_json(response)
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'code':4,
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
        
    @http.route('/jt_api/hr/location_checkin/locations/delete', type='http', auth='none', methods=['POST'], csrf=False)
    def location_checkin_locations_delete(self, **kw):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            employee    = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            employee_id = employee.id
            
            item_id = kw.get('item_id')
            
            record = request.env['jtemployees.location.areas'].sudo().search([
                (
                    "id", "=", item_id
                ),
                (
                    "employee_id", "=", employee_id
                )
            ], limit=1)
            
            if record:
                record.unlink()
            
            response = {
                'status': 'success',
                'data': item_id
            }
            return self.response_json(response)
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'code':4,
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
        
    @http.route('/jt_api/hr/location_checkin/locations/set_employee', type='http', auth='none', methods=['POST'], csrf=False)
    def location_checkin_locations_set_employee(self, **kw):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            employee    = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            employee_id = employee.id
            
            item_id = kw.get('item_id')
            child_employee_list = kw.get('employee_id')
            
            try:
                type = kw.get('type')
            except:
                type = "check_in"
            
            childs = self.get_all_childs(request=request, employee_id=employee_id)
            
            child_employee_list = str(child_employee_list)
            child_employee_list = child_employee_list.split(",")
            for child_employee_id in child_employee_list:
                child_employee_id = int(child_employee_id)
                
                if child_employee_id not in childs:
                    return self.response_json({'status': 'error', "code": 16,'message': 'You have no permission to change this request'})
                
                child_employee = request.env['hr.employee'].sudo().search([('id', '=', child_employee_id)], limit=1)
                
                record = request.env['jtemployees.location.areas'].sudo().search([
                    (
                        "id", "=", item_id
                    ),
                    (
                        "employee_id", "=", employee_id
                    )
                ], limit=1)
                
                if record:
                    if not type or type == "check_in":
                        child_employee.jt_location_area = record.id
                    else:
                        child_employee.jt_location_area_checkout = record.id
                    child_employee.jt_has_location_checkin = True
            
            response = {
                'status': 'success',
                'data': item_id
            }
            return self.response_json(response)
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'code':4,
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
        
    @http.route('/jt_api/hr/location_checkin/locations/unset_employee', type='http', auth='none', methods=['POST'], csrf=False)
    def location_checkin_locations_unset_employee(self, **kw):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            employee    = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            
            child_employee_list = kw.get('employee_id')
            
            try:
                type = kw.get('type')
            except:
                type = "check_in"
            
            childs = self.get_all_childs(request=request, employee_id=employee.id)
            
            child_employee_list = str(child_employee_list)
            child_employee_list = child_employee_list.split(",")
            for child_employee_id in child_employee_list:
                child_employee_id = int(child_employee_id)
                
                if child_employee_id not in childs:
                    return self.response_json({'status': 'error', "code": 16,'message': 'You have no permission to change this request'})
                
                child_employee = request.env['hr.employee'].sudo().search([('id', '=', child_employee_id)], limit=1)
                
                if not type or type == "check_in":
                    child_employee.jt_location_area = False
                else:
                    child_employee.jt_location_area_checkout = False
                if not child_employee.jt_location_area and not child_employee.jt_location_area_checkout:
                    child_employee.jt_has_location_checkin = False
            
            response = {
                'status': 'success'
            }
            return self.response_json(response)
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'code':4,
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
        
    @http.route('/jt_api/hr/location_checkin/attendance', type='http', auth='none', methods=['GET'], csrf=False)
    def location_attendance(self, **kw):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            
            page = kw.get('page')
            
            employee_id = False
            try:
                employee_id        = kw.get('employee_id')
            except:
                pass
            
            item_id = False
            try:
                item_id        = kw.get('item_id')
            except:
                pass
            
            if not page:
                page = 1
            else:
                page = int(page)
                
            page_size = 25
            start = (page - 1) * page_size
            end = start + page_size
            
            main_employee    = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            if employee_id:
                childs = self.get_all_childs(request=request, employee_id=main_employee.id)
                if int(employee_id) not in childs:
                    return self.response_json({'status': 'error', "code": 16, 'message': 'You have no permission to change this request'})
                employee    = request.env['hr.employee'].sudo().search([('id', '=', employee_id)], limit=1)
            else:
                employee    = main_employee
            
            employee_location_area = employee.jt_location_area
            #if not employee.jt_has_location_checkin or not employee_location_area:
            #    return self.response_json({'status': 'error', 'code': 41, 'message': 'Location check in not enabled for your account'})
            search_domain = [
                (
                    'employee_id', '=', employee.id
                )
                ]
            
            if item_id:
                search_domain.append((
                    'id', '=', item_id
                ))
            
            records = request.env['jtemployees.fd.log'].sudo().search(search_domain,
            limit=page_size, 
            offset=start, 
            order='check_in desc')
            
            records_count = request.env['jtemployees.fd.log'].sudo().search_count(search_domain)
            
            last_page = math.ceil(records_count / page_size)
                
            results = []
            
            local_tz = timezone('Asia/Baghdad')
            current_date_time = datetime.now()
            current_date_time = local_tz.localize(current_date_time).replace(tzinfo=None)
                    
            current_day = datetime.now(timezone("Asia/Baghdad")).strftime('%Y-%m-%d')
            
            for record in records:
                # we delete old none checkout
                show_record = True
                skip_hide_record = False # to skip one record that is still not checked out
                
                payload = {
                    "id": record.id
                }
                
                if show_record:
                    day = False
                    check_in = record.check_in
                    check_out = record.check_out
                    if check_in:
                        check_in = record.check_in.astimezone(ZoneInfo("Asia/Baghdad")).strftime('%Y-%m-%d %H:%M:%S')
                        day = record.check_in.astimezone(ZoneInfo("Asia/Baghdad")).strftime('%Y-%m-%d')
                        if item_id:
                            image = record.image
                            if image:
                                # Convert binary image to base64
                                image = "data:image/png;base64," + image.decode()
                            else:
                                image = None  # Fallback if no image is available
                            
                            payload['check_in_data'] = {
                                "latitude": record.latitude,
                                "longitude": record.longitude,
                                "image": image
                            }
                    if check_out:
                        check_out = record.check_out.astimezone(ZoneInfo("Asia/Baghdad")).strftime('%Y-%m-%d %H:%M:%S')
                        
                        if item_id:
                            image = record.image_checkout
                            if image:
                                # Convert binary image to base64
                                image = "data:image/png;base64," + image.decode()
                            else:
                                image = None  # Fallback if no image is available
                                
                            payload['check_out_data'] = {
                                "latitude": record.latitude_checkout,
                                "longitude": record.longitude_checkout,
                                "image": image
                            }
                        
                    if not check_out and current_day != day:
                        if skip_hide_record:
                            continue
                        skip_hide_record = True
                        
                    payload['date'] = day
                    payload['check_in'] = check_in
                    payload['check_out'] = check_out
                    payload['working_schedule'] = False
                    payload['attendance'] = False
                    
                    if record.schedule_id:
                        schedule_id = record.schedule_id
                        payload['working_schedule'] = {
                            "id": schedule_id.id,
                            "name": schedule_id.name,
                            "from": schedule_id.jt_start_time,
                            "to": schedule_id.jt_end_time
                        }
                    if record.attendance_id:
                        attendance_id = record.attendance_id
                        payload['attendance'] = {
                            "id": attendance_id.id,
                            "hours_per_day": attendance_id.jt_hours_per_day,
                            "shortage_hours": attendance_id.jt_shortage_hours,
                        }
                        
                    results.append(payload)
            
            response = {
                'status': 'success',
                    'data': results,
                    'current_page': page,
                    'last_page': last_page
                }
            return self.response_json(response)
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'code':4,
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
       
    def get_all_childs(self, request, employee_id, visited=None):
        if visited is None:
            visited = set()
        
        if employee_id in visited:
            return []
        visited.add(employee_id)
        
        ids = []
        employees = request.env['hr.employee'].sudo().search([('parent_id', '=', employee_id)])
        for item in employees:
            ids.append(item.id)
            new_ids = self.get_all_childs(request=request, employee_id=item.id, visited=visited)
            for item2 in new_ids:
                ids.append(item2)
        return ids
    

    def make_square(self, json_string):
        """
        Takes JSON string:
        {"north": ..., "south": ..., "east": ..., "west": ...}

        Returns dict with corrected square bounds.
        """

        data = json.loads(json_string)

        south = float(data["south"])
        north = float(data["north"])
        east  = float(data["east"])
        west  = float(data["west"])

        R = 6371000  # Earth radius in meters

        def distance(lat1, lon1, lat2, lon2):
            lat1, lon1, lat2, lon2 = map(math.radians, (lat1, lon1, lat2, lon2))
            dlat = lat2 - lat1
            dlon = lon2 - lon1
            a = (
                math.sin(dlat/2)**2 +
                math.cos(lat1) * math.cos(lat2) * math.sin(dlon/2)**2
            )
            return 2 * R * math.atan2(math.sqrt(a), math.sqrt(1 - a))

        # center point
        center_lat = (north + south) / 2
        center_lon = (east + west) / 2

        # current dimensions
        height = distance(north, center_lon, south, center_lon)
        width  = distance(center_lat, west, center_lat, east)

        # use larger side
        half_size = max(width, height) / 2

        # meters → degrees
        lat_delta = (half_size / R) * (180 / math.pi)
        lon_delta = (half_size / (R * math.cos(math.radians(center_lat)))) * (180 / math.pi)

        result = {
            "north": center_lat + lat_delta,
            "south": center_lat - lat_delta,
            "east":  center_lon + lon_delta,
            "west":  center_lon - lon_delta
        }

        return result


    def response_json(self, responseData):
        response = http.Response(
                json.dumps(responseData, default=str),
                status=200,
                mimetype='application/json'
            )
        response.headers['Access-Control-Allow-Origin'] = '*'
        response.headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS, PUT, DELETE'
        response.headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization, Origin, X-Requested-With, Accept'
        return response
    
    def get_user(self, request):
        if 'Authorization' not in request.httprequest.headers:
            return False
                
        token = request.httprequest.headers['Authorization']
        return request.env['jtapi.users'].sudo().auth_user(token, ['hr'])
