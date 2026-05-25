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
from pytz import timezone
import json
import math

class HRProfileController(http.Controller):

    @http.route('/jthr/profile', type='http', auth='user', website=True)
    def hr_profile(self, **kw):
        employee = request.env.user.employee_id
        result = False
        
        wall_records = request.env['jtemployees.wall'].sudo().search([
            ('deleted', '=', False),
            ('expires', '>', datetime.now()),
            ('departments', 'in', [employee.department_id.id])
            ], limit=25, order='id desc')
        
        wall_results = []
            
        for item in wall_records:
            image_base64 = None
            if item.image:
                # Prepare the employee image
                image = item.image  # Use the appropriate image field (e.g., image_1920)
                if image:
                    # Convert binary image to base64
                    image_base64 = "data:image/png;base64," + image.decode()
            wall_results.append({
                "id": item.id,
                "name": item.name,
                "details": item.details,
                "image":   image_base64,
                "created_at": item.create_date,
            })
            
        if employee:
            # Prepare the employee image
            employee_image = employee.image_128  # Use the appropriate image field (e.g., image_1920)
            if employee_image:
                # Convert binary image to base64
                employee_image_base64 = "data:image/png;base64," + employee_image.decode()
            else:
                employee_image_base64 = None  # Fallback if no image is available
                
            attendance_info = employee.attendance_info()
            
            if attendance_info and 'shortages_details' in attendance_info:
                index = 0
                for item in attendance_info['shortages_details']:
                    attendance_info['shortages_details'][index]['hours'] = self.human_readable_hours(attendance_info['shortages_details'][index]['hours'])
                    index = index + 1
                    
                attendance_info['over_time_hours'] = self.human_readable_hours(attendance_info['over_time_hours'])
                attendance_info['time_off_requested'] = self.human_readable_hours(attendance_info['time_off_requested'])
                attendance_info['leave_days_requested'] = self.human_readable_hours(attendance_info['leave_days_requested'])
                attendance_info['hour_per_day'] = self.human_readable_hours(attendance_info['hour_per_day'])
                attendance_info['shortage_hours'] = self.human_readable_hours(attendance_info['shortage_hours'])
                
            
            extra_allocations = request.env['jtemployees.extraallocations'].sudo().search([
            ('deleted', '=', False),
            ('employee_id', '=', employee.id)]).read(fields=['id', 'name', 'amount'])
                
            result = {
                'id':  employee.id,
                'name':  employee.name,
                'full_name':  employee.full_name,
                'barcode':  employee.barcode,
                'pin':  employee.pin,
                'image':  employee_image_base64,
                'family_name': employee.family_name,
                'department': employee.department_id.read(fields=['id', 'name']),
                'parent':  employee.parent_id.read(fields=['id', 'name', 'job_title']),
                'has_team': len(employee.child_ids) > 0,
                'current_allocations': {
                    'leave_days': employee.jt_leavedays,
                    'timeoffs': employee.jt_timeoff_readable,
                    'points': employee.jt_current_points
                },
                'location_checkin': employee.jt_has_location_checkin,
                'attendance_info': attendance_info,
                'extra_salary_allocations': extra_allocations,
                'position': employee.job_id.name,
                'title': employee.job_title,
                'badge_id': employee.barcode,
                'join_date': employee.jt_join_date
            }
            
        return request.render('jtemployees.dashboard_template', {'result': result, 'wall_result': wall_results})
    
    def human_readable_hours(self, data):
        mins = data * 60
        hours = int(mins // 60)
        mins = int(round(mins % 60))
        formatted_time = f"{hours:02d}:{mins:02d}"
        return formatted_time
